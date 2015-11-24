<?php

namespace Drupal\responsive_svg;

use Symfony\Component\DomCrawler\Crawler;

class ResponsiveSvgTwigExtension extends \Twig_Extension
{

  public function getFilters()
  {
    return array(
      new \Twig_SimpleFilter('responsiveSVG', array($this, 'generateResponsiveSvg'), ['is_safe' => ['html']]),
      new \Twig_SimpleFilter('responsiveSourceSVG', array($this, 'generateResponsiveSourceSvg'), ['is_safe' => ['html']]),
    );
  }

  private function resolvePath($path) {
    $module_config = \Drupal::config('responsive_svg.config');
    $mappings = $module_config->get('mappings');

    // Resolve mappings when no filename is given.
    $pathResolved = $path;
    if (isset($mappings[$path])) {
      $pathResolved = $mappings[$path]['path'];
    }

    // Support for the @themeName token in the path.
    preg_match('/@([^\/]+)/', $pathResolved, $matches);
    if (count($matches)) {
      $theme_name = $matches[1];
      $theme_path = drupal_get_path('theme', $theme_name);
      if (strlen($theme_path )> 0) {
        $pathResolved = str_replace('@' . $theme_name, $theme_path , $pathResolved);
      }
    }

    return $pathResolved;
  }

  private function loadContent($path) {
    $svg = file_get_contents(DRUPAL_ROOT . '/' . $path);

    return $svg;
  }

  public function generateResponsiveSourceSvg($uri)
  {
    $pathResolved = $this->resolvePath($uri);
    $svgContent = $this->loadContent($pathResolved);

    $svgContent = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $svgContent);
    $svgContent = str_replace('<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">', '', $svgContent);
    $svgContent = str_replace('<svg ', '<svg style="display:none;" ', $svgContent);

    return $svgContent;
  }

  public function generateResponsiveSvg($uri, $config = [])
  {
    $default = [
      'offsetX' => 0,
      'offsetY' => 0,
      'class' => ''
    ];

    $config = array_merge($default, (array) $config);

    list($path, $identifier) = explode('#', $uri);

    $module_config = \Drupal::config('responsive_svg.config');
    $mappings = $module_config->get('mappings');

    $pathResolved = $this->resolvePath($path);

    if (!empty($mappings[$path]['replacement'])) {
      $href = $mappings[$path]['replacement'];
    } else {
      $href = '/' . $pathResolved;
    }
    if (strlen($identifier) > 0) {
      $href .= '#' . $identifier;
    }

    // Parse svg file and read viewBox attribute.
    $svg = $this->loadContent($pathResolved);
    if ($svg === false) {
      drupal_set_message('Cannot find SVG ' . $uri);
      return '';
    }

    $crawler = new Crawler($svg);

    if (strlen($identifier) > 0) {
      $item = $crawler->filter('#' . $identifier);
    } else {
      $item = $crawler;
    }

    if (!$item->count()) {
      drupal_set_message('Cannot find SVG element for ' . $uri);
      return '';
    }

    $viewBox = $item->attr('viewBox');

    if (strlen($viewBox) == 0) {
      drupal_set_message('Cannot find viewBox attribute in ' . $uri);
      return '';
    }

    // Build markup
    list($x, $y, $width, $height) = explode(' ', $viewBox);

    $width += $config['offsetX'];
    $height += $config['offsetY'];

    if (isset($config['width'])) {
      $width = $config['width'];
    }

    if (isset($config['height'])) {
      $height = $config['height'];
    }

    $padding = round($height / $width * 100, 5);

    $classes = array_merge(['responsive-svg'], explode(' ', $config['class']));

    $dom = new \DOMDocument();

    $wrapper = $dom->createElement('div');
    $wrapper->setAttribute('class', implode(' ', $classes));
    $wrapper->setAttribute('style', 'position: relative;');

    $filler = $dom->createElement('div');
    $filler->setAttribute('style', 'width: 100%; height: 0; overflow-hidden; padding-bottom: ' . $padding . '%');
    $wrapper->appendChild($filler);

    if (strlen($identifier) > 0) {
      $svg = $dom->createElement('svg');

      if (isset($mappings[$path]['method']) && $mappings[$path]['method'] == 'inline') {
        // Inline SVG.
        $svg->setAttribute('viewBox', $viewBox);
        $content = $dom->createDocumentFragment();
        $content->appendXML($item->html());
        $svg->appendChild($content);
      } else {
        // Linked SVG.
        $svg->setAttribute('viewBox', implode(' ', [0, 0, $width, $height]));
        $use = $dom->createElement('use');
        $use->setAttribute('xlink:href', $href);
        $svg->appendChild($use);
      }
    } else {
      $svg = $dom->createElement('object');
      $svg->setAttribute('type', 'image/svg+xml');
      $svg->setAttribute('data', $href);
    }

    $svg->setAttribute('style', 'position: absolute; top: 0; bottom: 0; left: 0; right: 0;');

    $wrapper->appendChild($svg);
    $dom->appendChild($wrapper);

    return $dom->saveHTML();
  }

  public function getName()
  {
    return 'responsive_svg';
  }

}
