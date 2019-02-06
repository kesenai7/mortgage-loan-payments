<?php

namespace Drupal\mlp;

/**
 * Interface CalculateServiceInterface.
 */
interface CalculateServiceInterface {

  /**
   * Main function for MLP calculation.
   *
   * @param $values
   * List of array elements should be passed.
   *
   * @param bool $payments
   * Include payments data (TRUE by default) or not (FALSE).
   *
   * @return mixed
   */
  public function calculate($values, $payments = TRUE);
}
