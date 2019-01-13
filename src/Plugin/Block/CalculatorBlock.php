<?php

namespace Drupal\mlp\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'CalculatorBlock' block.
 *
 * @Block(
 *  id = "calculator_block",
 *  admin_label = @Translation("Calculator block"),
 * )
 */
class CalculatorBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Get custom form.
    $form = \Drupal::formBuilder()->getForm('Drupal\mlp\Form\CalculatorForm');

    return $form;
  }

}
