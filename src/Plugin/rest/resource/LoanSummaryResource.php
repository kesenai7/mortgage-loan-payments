<?php

namespace Drupal\mlp\Plugin\rest\resource;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\mlp\CalculateServiceInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "loan_summary_resource",
 *   label = @Translation("Loan summary resource"),
 *   uri_paths = {
 *     "canonical" = "/api/loan_summary"
 *   }
 * )
 */
class LoanSummaryResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Drupal\mlp\CalculateServiceInterface definition.
   *
   * @var \Drupal\mlp\CalculateServiceInterface
   */
  protected $mlpCalculate;

  /**
   * Constructs a new LoanSummaryResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    CalculateServiceInterface $mlp_calculate) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->mlpCalculate = $mlp_calculate;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('mlp'),
      $container->get('current_user'),
      $container->get('mlp.calculate')
    );
  }

  /**
   * Ensure that params are provided in this request.
   *
   * @param $params
   * Get request params.
   *
   */
  protected function ensureDataIsProvided($params) {
    // Custom basic validations.
    if (!isset($params['loan_amount']) || !is_numeric($params['loan_amount'])) {
      throw new BadRequestHttpException('`loan_amount` is not set or bad format. Example: 65000');
    }
    if (!isset($params['annual_interest_rate']) || !is_numeric($params['annual_interest_rate'])) {
      throw new BadRequestHttpException('`annual_interest_rate` is not set or bad format. Example: 20');
    }
    if (!isset($params['loan_period_in_years']) || !is_numeric($params['loan_period_in_years'])) {
      throw new BadRequestHttpException('`loan_period_in_years` is not set or bad format. Example: 30');
    }
    if (!isset($params['number_of_payments_per_year']) || !is_numeric($params['number_of_payments_per_year'])) {
      throw new BadRequestHttpException('`number_of_payments_per_year` is not set or bad format. Example: 12');
    }
    if (isset($params['start_date_of_loan'])) {
      try {
        DrupalDateTime::createFromFormat('d/m/Y', $params['start_date_of_loan']);
      } catch (\Exception $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
    }
    else {
      throw new BadRequestHttpException('`start_date_of_loan` is not set.');
    }
    if (!isset($params['optional_extra_payments']) || !is_numeric($params['optional_extra_payments'])) {
      throw new BadRequestHttpException('`optional_extra_payments` is not set or bad format. Example: 100');
    }
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get() {
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    // Get request params.
    $params = \Drupal::request()->query->all();

    // Ensure that all params are passed in correct format.
    $this->ensureDataIsProvided($params);

    // Create DrupalDateTime object from string.
    $start_date_of_loan = DrupalDateTime::createFromFormat('d/m/Y', $params['start_date_of_loan']);

    // Define arguments array.
    $args = [
      'loan_amount' => round($params['loan_amount'], 2),
      'annual_interest_rate' => $params['annual_interest_rate'],
      'loan_period_in_years' => $params['loan_period_in_years'],
      'number_of_payments_per_year' => $params['number_of_payments_per_year'],
      'start_date_of_loan' => $start_date_of_loan,
      'optional_extra_payments' => round($params['optional_extra_payments'], 2),
    ];

    // Pass FALSE to payments arg to exclude not necessary payments data.
    $data = $this->mlpCalculate->calculate($args, FALSE);

    $response = new ResourceResponse($data);
    // In order to generate fresh result every time (without clearing
    // the cache), you need to invalidate the cache.
    $response->addCacheableDependency($params);
    return $response;
  }
}
