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
   * @return mixed
   */
  public function calculate($values);
}
