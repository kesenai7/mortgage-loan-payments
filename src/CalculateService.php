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
   * @param bool $payments
   * Include payments data (TRUE by default) or not (FALSE).
   *
   * @return mixed
   */
  public function calculate($params, $payments = TRUE) {
    // Calculate interest variable which will be reused.
    $interest = ($params['annual_interest_rate'] / $params['number_of_payments_per_year']) / 100;

    // Calculate Scheduled Payment.
    $scheduled_payment = $this->calculateScheduledPayment($params['loan_period_in_years'], $params['number_of_payments_per_year'], $interest, $params['loan_amount']);
    $results['scheduled_payment'] = number_format($scheduled_payment, 2);

    // Calculate Scheduled Number of Payments.
    $results['scheduled_number_of_payments'] = $params['number_of_payments_per_year'] * $params['loan_period_in_years'];

    // Calculate list of payments.
    $data_args = [
      'loan_amount' => $params['loan_amount'],
      'scheduled_payment' => $scheduled_payment,
      'optional_extra_payments' => $params['optional_extra_payments'],
      'start_date_of_loan' => $params['start_date_of_loan'],
      'annual_interest_rate' => $params['annual_interest_rate'],
      'number_of_payments_per_year' => $params['number_of_payments_per_year'],
      'interest' => $interest,
    ];

    $payments_data = $this->calculatePaymentsData($data_args);

    // Make numbers format beautiful.
    // @todo Maybe there is a better way to apply format to numbers.
    $format_fields = [
      'beginning_balance',
      'scheduled_payment',
      'extra_payment',
      'total_payment',
      'principal',
      'interest',
      'ending_balance',
      'cumulative_interest',
    ];

    foreach ($payments_data['payments_data'] as $key => $row) {
      foreach ($row as $field_key => $field_value) {
        if (in_array($field_key, $format_fields)) {
          $payments_data['payments_data'][$key][$field_key] = number_format($field_value, 2);
        }
      }
    }

    // Actual Number of Payments.
    $results['actual_number_of_payments'] = count($payments_data['payments_data']);

    // Include payment data if $payments = TRUE.
    if ($payments) {
      $results['data'] = $payments_data;
    }

    // Total Early Payments (to have it in one results array).
    $results['total_early_payments'] = number_format($payments_data['total_early_payments'], 2);

    // Total Interest.
    $last_payment = end($payments_data['payments_data']);
    $results['total_interest'] = $last_payment['cumulative_interest'];

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
    return $amount;
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
      $end_interest = $balance * $params['interest'];
      $extra_payment = $params['optional_extra_payments'];

      $ending_balance = 0;

      $last_extra_payment = $balance - $params['scheduled_payment'];

      // If Sched_Pay+Scheduled_Extra_Payments<Beg_Bal.
      if (($params['scheduled_payment'] + $params['optional_extra_payments']) < $balance) {
        $total_payment = $params['scheduled_payment'] + $params['optional_extra_payments'];
        $principal = $total_payment - $end_interest;

        if (($last_extra_payment > 0)) {
          $extra_payment = $params['optional_extra_payments'];
        }

        $ending_balance = $balance - $principal;
      }
      else {
        $total_payment = $balance;
        $principal = $total_payment - $end_interest;
        $extra_payment = max($last_extra_payment, 0);
      }

      // Not a first loop.
      if (!empty($data)) {
        $payment_date[$i] = $payment_date[$i - 1]->modify('+1 month');
        $cumulative_interest = $end_interest + $data['payments_data'][$i - 1]['cumulative_interest'];
      }
      else {
        $cumulative_interest = $end_interest;
        $data['total_early_payments'] = 0;
      }

      $data['payments_data'][$i] = [
        'no' => $i,
        'payment_date' => $payment_date[$i]->format('d/m/Y'),
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
}
