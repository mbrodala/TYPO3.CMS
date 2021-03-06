<?php
namespace TYPO3\CMS\Backend\Form\Container;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lang\LanguageService;

/**
 * Handle palettes and single fields.
 *
 * This container is called by TabsContainer, NoTabsContainer and ListOfFieldsContainer.
 *
 * This container mostly operates on TCA showItem of a specific type - the value is
 * coming in from upper containers as "fieldArray". It handles palettes with all its
 * different options and prepares rendering of single fields for the SingleFieldContainer.
 */
class PaletteAndSingleContainer extends AbstractContainer
{
    /**
     * Final result array accumulating results from children and final HTML
     *
     * @var array
     */
    protected $resultArray = array();

    /**
     * Entry method
     *
     * @return array As defined in initializeResultArray() of AbstractNode
     */
    public function render()
    {
        $languageService = $this->getLanguageService();

        /**
         * The first code block creates a target structure array to later create the final
         * HTML string. The single fields and sub containers are rendered here already and
         * other parts of the return array from children except html are accumulated in
         * $this->resultArray
         *
        $targetStructure = array(
            0 => array(
                'type' => 'palette',
                'fieldName' => 'palette1',
                'fieldLabel' => 'palette1',
                'elements' => array(
                    0 => array(
                        'type' => 'single',
                        'fieldName' => 'paletteName',
                        'fieldLabel' => 'element1',
                        'fieldHtml' => 'element1',
                    ),
                    1 => array(
                        'type' => 'linebreak',
                    ),
                    2 => array(
                        'type' => 'single',
                        'fieldName' => 'paletteName',
                        'fieldLabel' => 'element2',
                        'fieldHtml' => 'element2',
                    ),
                ),
            ),
            1 => array(
                'type' => 'single',
                'fieldName' => 'element3',
                'fieldLabel' => 'element3',
                'fieldHtml' => 'element3',
            ),
            2 => array(
                'type' => 'palette2',
                'fieldName' => 'palette2',
                'fieldLabel' => '', // Palettes may not have a label
                'elements' => array(
                    0 => array(
                        'type' => 'single',
                        'fieldName' => 'element4',
                        'fieldLabel' => 'element4',
                        'fieldHtml' => 'element4',
                    ),
                    1 => array(
                        'type' => 'linebreak',
                    ),
                    2 => array(
                        'type' => 'single',
                        'fieldName' => 'element5',
                        'fieldLabel' => 'element5',
                        'fieldHtml' => 'element5',
                    ),
                ),
            ),
        );
         */

        // Create an intermediate structure of rendered sub elements and elements nested in palettes
        $targetStructure = array();
        $mainStructureCounter = -1;
        $fieldsArray = $this->data['fieldsArray'];
        $this->resultArray = $this->initializeResultArray();
        foreach ($fieldsArray as $fieldString) {
            $fieldConfiguration = $this->explodeSingleFieldShowItemConfiguration($fieldString);
            $fieldName = $fieldConfiguration['fieldName'];
            if ($fieldName === '--palette--') {
                $paletteElementArray = $this->createPaletteContentArray($fieldConfiguration['paletteName']);
                if (!empty($paletteElementArray)) {
                    $mainStructureCounter ++;
                    $targetStructure[$mainStructureCounter] = array(
                        'type' => 'palette',
                        'fieldName' => $fieldConfiguration['paletteName'],
                        'fieldLabel' => $languageService->sL($fieldConfiguration['fieldLabel']),
                        'elements' => $paletteElementArray,
                    );
                }
            } else {
                if (!is_array($this->data['processedTca']['columns'][$fieldName])) {
                    continue;
                }

                $options = $this->data;
                $options['fieldName'] = $fieldName;

                $options['renderType'] = 'singleFieldContainer';
                $childResultArray = $this->nodeFactory->create($options)->render();

                if (!empty($childResultArray['html'])) {
                    $mainStructureCounter ++;
                    $targetStructure[$mainStructureCounter] = array(
                        'type' => 'single',
                        'fieldName' => $fieldConfiguration['fieldName'],
                        'fieldLabel' => $this->getSingleFieldLabel($fieldName, $fieldConfiguration['fieldLabel']),
                        'fieldHtml' => $childResultArray['html'],
                    );
                }

                $childResultArray['html'] = '';
                $this->resultArray = $this->mergeChildReturnIntoExistingResult($this->resultArray, $childResultArray);
            }
        }

        // Compile final content
        $content = array();
        foreach ($targetStructure as $element) {
            if ($element['type'] === 'palette') {
                $paletteName = $element['fieldName'];
                $paletteElementsHtml = $this->renderInnerPaletteContent($element);

                $isHiddenPalette = !empty($this->data['processedTca']['palettes'][$paletteName]['isHiddenPalette']);

                $paletteElementsHtml = '<div class="row">' . $paletteElementsHtml . '</div>';

                $content[] = $this->fieldSetWrap($paletteElementsHtml, $isHiddenPalette, $element['fieldLabel']);
            } else {
                // Return raw HTML only in case of user element with no wrapping requested
                if ($this->isUserNoTableWrappingField($element)) {
                    $content[] = $element['fieldHtml'];
                } else {
                    $content[] = $this->fieldSetWrap($this->wrapSingleFieldContentWithLabelAndOuterDiv($element));
                }
            }
        }

        $finalResultArray = $this->resultArray;
        $finalResultArray['html'] = implode(LF, $content);
        return $finalResultArray;
    }

    /**
     * Render single fields of a given palette
     *
     * @param string $paletteName The palette to render
     * @return array
     */
    protected function createPaletteContentArray($paletteName)
    {
        // palette needs a palette name reference, otherwise it does not make sense to try rendering of it
        if (empty($paletteName) || empty($this->data['processedTca']['palettes'][$paletteName]['showitem'])) {
            return array();
        }

        $resultStructure = array();
        $foundRealElement = false; // Set to true if not only line breaks were rendered
        $fieldsArray = GeneralUtility::trimExplode(',', $this->data['processedTca']['palettes'][$paletteName]['showitem'], true);
        foreach ($fieldsArray as $fieldString) {
            $fieldArray = $this->explodeSingleFieldShowItemConfiguration($fieldString);
            $fieldName = $fieldArray['fieldName'];
            if ($fieldName === '--linebreak--') {
                $resultStructure[] = array(
                    'type' => 'linebreak',
                );
            } else {
                if (!is_array($this->data['processedTca']['columns'][$fieldName])) {
                    continue;
                }
                $options = $this->data;
                $options['fieldName'] = $fieldName;

                $options['renderType'] = 'singleFieldContainer';
                $singleFieldContentArray = $this->nodeFactory->create($options)->render();

                if (!empty($singleFieldContentArray['html'])) {
                    $foundRealElement = true;
                    $resultStructure[] = array(
                        'type' => 'single',
                        'fieldName' => $fieldName,
                        'fieldLabel' => $this->getSingleFieldLabel($fieldName, $fieldArray['fieldLabel']),
                        'fieldHtml' => $singleFieldContentArray['html'],
                    );
                    $singleFieldContentArray['html'] = '';
                }
                $this->resultArray = $this->mergeChildReturnIntoExistingResult($this->resultArray, $singleFieldContentArray);
            }
        }

        if ($foundRealElement) {
            return $resultStructure;
        } else {
            return array();
        }
    }

    /**
     * Renders inner content of single elements of a palette and wrap it as needed
     *
     * @param array $elementArray Array of elements
     * @return string Wrapped content
     */
    protected function renderInnerPaletteContent(array $elementArray)
    {
        // Group fields
        $groupedFields = array();
        $row = 0;
        $lastLineWasLinebreak = true;
        foreach ($elementArray['elements'] as $element) {
            if ($element['type'] === 'linebreak') {
                if (!$lastLineWasLinebreak) {
                    $row++;
                    $groupedFields[$row][] = $element;
                    $row++;
                    $lastLineWasLinebreak = true;
                }
            } else {
                $lastLineWasLinebreak = false;
                $groupedFields[$row][] = $element;
            }
        }

        $result = array();
        // Process fields
        foreach ($groupedFields as $fields) {
            $numberOfItems = count($fields);
            $colWidth = (int)floor(12 / $numberOfItems);
            // Column class calculation
            $colClass = 'col-md-12';
            $colClear = array();
            if ($colWidth == 6) {
                $colClass = 'col-sm-6';
                $colClear = array(
                    2 => 'visible-sm-block visible-md-block visible-lg-block',
                );
            } elseif ($colWidth === 4) {
                $colClass = 'col-sm-4';
                $colClear = array(
                    3 => 'visible-sm-block visible-md-block visible-lg-block',
                );
            } elseif ($colWidth === 3) {
                $colClass = 'col-sm-6 col-md-3';
                $colClear = array(
                    2 => 'visible-sm-block',
                    4 => 'visible-md-block visible-lg-block',
                );
            } elseif ($colWidth <= 2) {
                $colClass = 'checkbox-column col-sm-6 col-md-3 col-lg-2';
                $colClear = array(
                    2 => 'visible-sm-block',
                    4 => 'visible-md-block',
                    6 => 'visible-lg-block'
                );
            }

            // Render fields
            for ($counter = 0; $counter < $numberOfItems; $counter++) {
                $element = $fields[$counter];
                if ($element['type'] === 'linebreak') {
                    if ($counter !== $numberOfItems) {
                        $result[] = '<div class="clearfix"></div>';
                    }
                } else {
                    $result[] = $this->wrapSingleFieldContentWithLabelAndOuterDiv($element, array($colClass));

                    // Breakpoints
                    if ($counter + 1 < $numberOfItems && !empty($colClear)) {
                        foreach ($colClear as $rowBreakAfter => $clearClass) {
                            if (($counter + 1) % $rowBreakAfter === 0) {
                                $result[] = '<div class="clearfix ' . $clearClass . '"></div>';
                            }
                        }
                    }
                }
            }
        }

        return implode(LF, $result);
    }

    /**
     * Wrap content in a field set
     *
     * @param string $content Incoming content
     * @param bool $paletteHidden TRUE if the palette is hidden
     * @param string $label Given label
     * @return string Wrapped content
     */
    protected function fieldSetWrap($content, $paletteHidden = false, $label = '')
    {
        $fieldSetClass = 'form-section';
        if ($paletteHidden) {
            $fieldSetClass = 'hide';
        }

        $result = array();
        $result[] = '<fieldset class="' . $fieldSetClass . '">';

        if (!empty($label)) {
            $result[] = '<h4 class="form-section-headline">' . htmlspecialchars($label) . '</h4>';
        }

        $result[] = $content;
        $result[] = '</fieldset>';
        return implode(LF, $result);
    }

    /**
     * Wrap a single element
     *
     * @param array $element Given element as documented above
     * @param array $additionalPaletteClasses Additional classes to be added to HTML
     * @return string Wrapped element
     */
    protected function wrapSingleFieldContentWithLabelAndOuterDiv(array $element, array $additionalPaletteClasses = array())
    {
        $fieldName = $element['fieldName'];

        $paletteFieldClasses = array(
            'form-group',
            't3js-formengine-validation-marker',
            't3js-formengine-palette-field',
        );
        foreach ($additionalPaletteClasses as $class) {
            $paletteFieldClasses[] = $class;
        }

        $label = BackendUtility::wrapInHelp($this->data['tableName'], $fieldName, htmlspecialchars($element['fieldLabel']));

        $content = array();
        $content[] = '<div class="' . implode(' ', $paletteFieldClasses) . '">';
        $content[] =    '<label class="t3js-formengine-label">';
        $content[] =        $label;
        $content[] =    '</label>';
        $content[] =    $element['fieldHtml'];
        $content[] = '</div>';

        return implode(LF, $content);
    }

    /**
     * Determine label of a single field (not a palette label)
     *
     * @param string $fieldName The field name to calculate the label for
     * @param string $labelFromShowItem Given label, typically from show item configuration
     * @return string Field label
     */
    protected function getSingleFieldLabel($fieldName, $labelFromShowItem)
    {
        $languageService = $this->getLanguageService();
        $table = $this->data['tableName'];
        $label = $labelFromShowItem;
        if (!empty($this->data['processedTca']['columns'][$fieldName]['label'])) {
            $label = $this->data['processedTca']['columns'][$fieldName]['label'];
        }
        if (!empty($labelFromShowItem)) {
            $label = $labelFromShowItem;
        }

        $fieldTSConfig = [];
        if (isset($this->data['pageTsConfig']['TCEFORM.'][$table . '.'][$fieldName . '.'])
            && is_array($this->data['pageTsConfig']['TCEFORM.'][$table . '.'][$fieldName . '.'])
        ) {
            $fieldTSConfig = $this->data['pageTsConfig']['TCEFORM.'][$table . '.'][$fieldName . '.'];
        }

        if (!empty($fieldTSConfig['label'])) {
            $label = $fieldTSConfig['label'];
        }
        if (!empty($fieldTSConfig['label.'][$languageService->lang])) {
            $label = $fieldTSConfig['label.'][$languageService->lang];
        }
        return $languageService->sL($label);
    }

    /**
     * TRUE if field is of type user and to wrapping is requested
     *
     * @param array $element Current element from "target structure" array
     * @return bool TRUE if user and noTableWrapping is set
     */
    protected function isUserNoTableWrappingField($element)
    {
        $fieldName = $element['fieldName'];
        if (
            $this->data['processedTca']['columns'][$fieldName]['config']['type'] === 'user'
            && !empty($this->data['processedTca']['columns'][$fieldName]['config']['noTableWrapping'])
        ) {
            return true;
        }
        return false;
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
