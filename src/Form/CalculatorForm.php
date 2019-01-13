<?php

namespace Drupal\mlp\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\mlp\CalculateServiceInterface;

/**
 * Class CalculatorForm.
 */
class CalculatorForm extends FormBase {

  /**
   * Drupal\mlp\CalculateServiceInterface definition.
   *
   * @var \Drupal\mlp\CalculateServiceInterface
   */
  protected $mlpCalculate;

  /**
   * Constructs a new CalculatorForm object.
   */
  public function __construct(CalculateServiceInterface $mlp_calculate) {
    $this->mlpCalculate = $mlp_calculate;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mlp.calculate')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'calculator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="mlp_form_wrapper">';
    $form['#suffix'] = '</div>';

    $form['loan_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Loan Amount'),
      '#description' => $this->t('For example: 65000'),
      '#required' => TRUE,
      '#weight' => '0',
      '#default_value' => 65000,
    ];

    $form['annual_interest_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('Annual Interest Rate'),
      '#description' => $this->t('For example: 20'),
      '#required' => TRUE,
      '#weight' => '1',
      '#default_value' => 20,
    ];

    $form['loan_period_in_years'] = [
      '#type' => 'number',
      '#title' => $this->t('Loan Period in Years'),
      '#description' => $this->t('For example: 30'),
      '#required' => TRUE,
      '#weight' => '2',
      '#default_value' => 30,
    ];

    $form['number_of_payments_per_year'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of Payments Per Year'),
      '#description' => $this->t('For example: 12'),
      '#required' => TRUE,
      '#weight' => '3',
      '#default_value' => 12,
    ];

    $form['start_date_of_loan'] = [
      '#type' => 'date',
      '#title' => $this->t('Start Date of Loan'),
      '#description' => $this->t('For example: 22/01/2019'),
      '#required' => TRUE,
      '#weight' => '4',
    ];

    $form['optional_extra_payments'] = [
      '#type' => 'number',
      '#title' => $this->t('Optional Extra Payments'),
      '#description' => $this->t('For example: 100'),
      '#default_value' => 100,
      '#required' => TRUE,
      '#weight' => '5',
    ];

    $form['lender_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Lender Name'),
      '#description' => $this->t('For example: John'),
      '#maxlength' => 64,
      '#size' => 64,
      '#required' => TRUE,
      '#weight' => '6',
      '#default_value' => 'John',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#weight' => '7',
      '#ajax' => [
        'callback' => [$this, 'ajaxRebuildForm'],
        'wrapper' => 'mlp_form_wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => t('Calculating...'),
        ],
      ],
    ];

    $form['payment_data'] = [
      '#markup' => t('Please fill the data above to see the results.'),
      '#weight' => '9',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Custom validation.
    if ($form_state->getValue('loan_amount') <= 0) {
      $form_state->setErrorByName('loan_amount', $this->t('Loan amount cant\'t be 0 or less than 0.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Create DrupalDateTime object from string.
    $start_date_of_loan = DrupalDateTime::createFromFormat('Y-m-d', $form_state->getValue(['start_date_of_loan']));

    // Define arguments array.
    $args = [
      'loan_amount' => round($form_state->getValue('loan_amount'), 2),
      'annual_interest_rate' => $form_state->getValue('annual_interest_rate'),
      'loan_period_in_years' => $form_state->getValue('loan_period_in_years'),
      'number_of_payments_per_year' => $form_state->getValue('number_of_payments_per_year'),
      'start_date_of_loan' => $start_date_of_loan,
      'optional_extra_payments' => round($form_state->getValue('optional_extra_payments'), 2),
    ];

    $results = $this->mlpCalculate->calculate($args);

    // Build Hello message.
    $form['hello'] = [
      '#markup' => $this->t('Hello @name. Here is your mortgage loan payments table:', ['@name' => $form_state->getValue('lender_name')]),
      '#weight' => '7',
    ];

    // Build table for Loan Summary.
    $form['loan_summary'] = [
      '#type' => 'table',
      '#caption' => $this->t('Mortgage Loan Payments'),
      '#header' => [
        $this->t('Scheduled Payment'),
        $this->t('Scheduled Number of Payments'),
        $this->t('Actual Number of Payments'),
        $this->t('Total Early Payments'),
        $this->t('Total Interest'),
      ],
      '#rows' => [
        0 => [
          $results['scheduled_payment'],
          $results['scheduled_number_of_payments'],
          $results['actual_number_of_payments'],
          $results['data']['total_early_payments'],
          $results['total_interest'],
        ],
      ],
      '#weight' => '9',
    ];

    // Build table for payments data.
    $form['payment_data'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('PmtNo.'),
        $this->t('Payment Date'),
        $this->t('Beginning Balance'),
        $this->t('Scheduled Payment'),
        $this->t('Extra Payment'),
        $this->t('Total Payment'),
        $this->t('Principal'),
        $this->t('Interest'),
        $this->t('Ending Balance'),
        $this->t('Cumulative Interest'),
      ],
      '#rows' => $results['data']['payments_data'],
      '#weight' => '10',
    ];
  }

  /**
   * Ajax rebuild form function.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function ajaxRebuildForm(array &$form, FormStateInterface $form_state): array {
    return $form;
  }
}
