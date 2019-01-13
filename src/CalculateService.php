<?php

namespace Drupal\mlp;

use Psr\Log\LoggerInterface;

/**
 * Class CalculateService.
 */
class CalculateService implements CalculateServiceInterface {

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new CalculateService object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Main function for MLP calculation.
   *
   * @param $params
   * List of array elements should be passed:
   * $params['loan_amount'] Loan amount float value.
   * $params['annual_interest_rate']  Anual interest rate float value.
   * $params['loan_period_in_years']  Loan period in years int value.
   * $params['number_of_payments_per_year'] Number of payments per year int
   *   value.
   * $params['start_date_of_loan']  Start date of loan DrupalDateTime object.
   * $params['optional_extra_payments'] Optional extra payments float value.
   *
   * @return mixed
   */
  public function calculate($params) {
    // Calculate interest variable which will be reused.
    $interest = ($params['annual_interest_rate'] / $params['number_of_payments_per_year']) / 100;

    // Calculate Scheduled Payment.
    $results['scheduled_payment'] = $this->calculateScheduledPayment($params['loan_period_in_years'], $params['number_of_payments_per_year'], $interest, $params['loan_amount']);

    // Calculate Scheduled Number of Payments.
    $results['scheduled_number_of_payments'] = $params['number_of_payments_per_year'] * $params['loan_period_in_years'];

    // Calculate list of payments.
    $data_args = [
      'loan_amount' => $params['loan_amount'],
      'scheduled_payment' => $results['scheduled_payment'],
      'optional_extra_payments' => $params['optional_extra_payments'],
      'start_date_of_loan' => $params['start_date_of_loan'],
      'annual_interest_rate' => $params['annual_interest_rate'],
      'number_of_payments_per_year' => $params['number_of_payments_per_year'],
      'interest' => $interest,
    ];

    $payments_data = $this->calculatePaymentsData($data_args);
    $results['data'] = $payments_data;

    // Actual Number of Payments.
    $results['actual_number_of_payments'] = count($results['data']['payments_data']);

    // Total Interest.
    $results['total_interest'] = end($results['data']['payments_data'])['cumulative_interest'];

    // Log this query to custom channel. @todo Log more information.
    $this->logger->notice('User (UID: @uid) made calculations for this loan amount: @loan_amount.', [
      '@uid' => \Drupal::currentUser()->id(),
      '@loan_amount' => $params['loan_amount'],
    ]);

    return $results;
  }

  /**
   * Calculations of scheduled payment.
   *
   * @param int $loan_period_in_years
   * Loan period in years.
   * @param int $number_of_payments_per_year
   * Number of payments per year.
   * @param float $interest
   * Interest value calculated by formula:
   * Annual_interest_rate / Number_of_payments_per_year) / 100.
   * @param int $loan_amount
   * Loan amount.
   *
   * @return float
   */
  public static function calculateScheduledPayment($loan_period_in_years, $number_of_payments_per_year, $interest, $loan_amount) {
    $months = $loan_period_in_years * $number_of_payments_per_year;

    return self::calculatePmt($interest, $months, $loan_amount);
  }

  /**
   * Calculate PMT value.
   *
   * @param float $interest
   * Interest value calculated:
   * Annual_interest_rate / Number_of_payments_per_year) / 100.
   * @param int $months
   * Number of months calculated:
   * Loan_period_in_years * Number_of_payments_per_year
   * @param float $loan
   * Loan amount.
   *
   * @return float
   */
  public static function calculatePmt($interest, $months, $loan) {
    $amount = $interest * -$loan * pow((1 + $interest), $months) / (1 - pow((1 + $interest), $months));

    return static::round_up($amount, 2);
  }

  /**
   * Calculate payments data.
   *
   * @param array $params
   * List of array elements should be passed:
   * $params['loan_amount'] Loan amount float value.
   * $params['scheduled_payment] Float value calculated by
   *   'calculateScheduledPayment' method.
   * $params['optional_extra_payments'] Optional extra payments float value.
   * $params['start_date_of_loan'] Start date of loan DrupalDateTime object.
   * $params['annual_interest_rate']  Annual interest rate float value.
   * $params['number_of_payments_per_year'] Number of payments per year int
   *   value.
   * $params['interest'] Interest int value calculated:
   * Annual_interest_rate / Number_of_payments_per_year) / 100.
   *
   * @return mixed
   */
  public function calculatePaymentsData($params) {
    // Define init variables.
    $data = [];
    $balance = $params['loan_amount'];
    $i = 1;

    // Modify date for first payment.
    $payment_date[$i] = $params['start_date_of_loan']->modify('+1 month');

    // Calculations for all data rows.
    while ($balance > 0) {
      $total_payment = $params['scheduled_payment'] + $params['optional_extra_payments'];
      $end_interest = static::round_up($balance * $params['interest'], 2);
      $principal = static::round_up($total_payment - $end_interest, 2);

      $extra_payment = $params['optional_extra_payments'];

      $ending_balance = 0;

      // If Sched_Pay+Scheduled_Extra_Payments<Beg_Bal.
      if (($params['scheduled_payment'] + $params['optional_extra_payments']) < $balance) {
        // If Beg_Bal-Sched_Pay>0.
        if (($balance - $params['scheduled_payment']) > 0) {
          $extra_payment = $params['optional_extra_payments'];
        }
        $ending_balance = static::round_up($balance - $principal, 2);
      }
      else {
        $extra_payment = 0;
      }

      // Not a first loop.
      if (!empty($data)) {
        $payment_date[$i] = $payment_date[$i - 1]->modify('+1 month');
        $cumulative_interest = $end_interest + $data['payments_data'][$i - 1]['cumulative_interest'];
      }
      else {
        $cumulative_interest = $end_interest;
      }

      $data['payments_data'][$i] = [
        'no' => $i,
        'payment_date' => $payment_date[$i]->format('Y-m-d'),
        'beginning_balance' => $balance,
        'scheduled_payment' => $params['scheduled_payment'],
        'extra_payment' => $extra_payment,
        'total_payment' => $total_payment,
        'principal' => $principal,
        'interest' => $end_interest,
        'ending_balance' => $ending_balance,
        'cumulative_interest' => $cumulative_interest,
      ];

      $data['total_early_payments'] = $data['total_early_payments'] + $data['payments_data'][$i]['extra_payment'];

      $balance = $ending_balance;
      $i++;
    }

    return $data;
  }

  /**
   * Excel-like ROUNDUP function.
   *
   * @param $value
   * @param $places
   *
   * @return float|int
   */
  public static function round_up($value, $places)
  {
    $mult = pow(10, abs($places));
    return $places < 0 ?
      ceil($value / $mult) * $mult :
      ceil($value * $mult) / $mult;
  }
}
