<?php

/**
 * @file
 * Contains Drupal\responsive_svg\Form\ConfigurationForm.
 */

namespace Drupal\responsive_svg\Form;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Cmf\Component\Routing\ContentAwareGenerator;

/**
 * Class ConfigurationForm.
 *
 * @package Drupal\responsive_svg\Form
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'responsive_svg.config'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'responsive_svg_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('responsive_svg.config');
    $conf = $config->get();
    $config_text = Yaml::encode($conf);

    if (!\Drupal::moduleHandler()->moduleExists('yaml_editor')) {
      $message = $this->t('It is recommended to install the <a href="@yaml-editor">YAML Editor</a> module for easier editing.', [
        '@yaml-editor' => 'https://www.drupal.org/project/yaml_editor',
      ]);

      drupal_set_message($message, 'warning');
    }

    $form['config'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Configuration'),
      '#default_value' => $config_text,
      '#attributes' => ['data-yaml-editor' => 'true'],
    );

    $form['example'] = [
      '#type' => 'details',
      '#title' => $this->t('Example structure'),
    ];
    $form['example']['description'] = [
      '#prefix' => '<p>',
      '#suffix' => '</p>',
      '#markup' => $this->t('Each SVG file name has a path. Use <code>@theme_name</code> for referring to a theme.')
    ];
    $form['example']['code'] = [
      '#prefix' => '<pre>',
      '#suffix' => '</pre>',
      '#markup' => "mappings:
  logo:
    path: '@bartik/logo.svg'",
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $config_text = $form_state->getValue('config') ?: 'mappings:';
    try {
      $form_state->set('config', Yaml::decode($config_text));
    }
    catch (InvalidDataTypeException $e) {
      $form_state->setErrorByName('config', $e->getMessage());
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $form_state->get('config');
    $this->config('responsive_svg.config')
      ->setData($config)
      ->save();

    parent::submitForm($form, $form_state);
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
