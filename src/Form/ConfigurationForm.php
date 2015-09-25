<?php

/**
 * @file
 * Contains Drupal\responsive_svg\Form\ConfigurationForm.
 */

namespace Drupal\responsive_svg\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ConfigurationForm.
 *
 * @package Drupal\responsive_svg\Form
 */
class ConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'responsive_svg.configuration'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('responsive_svg.configuration');

    $mappings = $config->get('mappings');
    $mappings_text = $this->getMappingsText($mappings);

    $form['mappings'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('SVG mapping'),
      '#description' => $this->t('Use this field to store mappings to your svg files. Use "name|path" as pattern. E.g. "iconstack|@mythemeName/images/iconstack.svg"')
        . '<br/>' . $this->t('Optionally you can add a third parameter "name|path|replacement" to alter the svg link url. Useful for linking of inline svgs or from CDN.'),
      '#default_value' => $mappings_text,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $mappings = $this->getConfigurationArray($form_state->getValue('mappings'));

    $this->config('responsive_svg.configuration')
      ->set('mappings', $mappings)
      ->save();
  }

  /**
   * Transforms a text in key|value|replacement format into an array of mappings.
   *
   * @param $mappings_text
   * @return array
   */
  private function getConfigurationArray($mappings_text) {
    $mappings = [];
    $lines = preg_split('/\n|\r/', $mappings_text, -1, PREG_SPLIT_NO_EMPTY);
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines, 'strlen');

    foreach($lines as $line) {
      $matches = array();
      if (preg_match('/^([^\s\|]+)\|([^\|]+)(?:\|(.*))?/', $line, $matches)) {
        $replacement = NULL;
        if (isset($matches[3])) {
          $replacement = $matches[3];
        }
        $mappings[$matches[1]] = [
          'id' => $matches[1],
          'path' => $matches[2],
          'replacement' => $replacement,
        ];
      }
    }

    return $mappings;
  }

  /**
   * Transforms an array of mappings into a text in key|value|replacement format.
   *
   * @param $mappings
   * @return string
   */
  private function getMappingsText($mappings) {
    $mappings_text = '';

    foreach ($mappings as $mapping) {
      $mappings_text .= $mapping['id'] . '|' . $mapping['path'];
      if (isset($mapping['replacement']) && ($mapping['replacement'] !== NULL)) {
        $mappings_text .= '|' . $mapping['replacement'];
      }
      $mappings_text .= "\n";
    }

    return $mappings_text;
  }
}
