<?php
/**
 * ahfproducts 
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the ecomteck.com license that is
 * available through the world-wide-web at this URL:
 * https://ahfproducts .com/
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    DamConsultants
 * @package     DamConsultants_Ahfproducts
 */
namespace DamConsultants\Ahfproducts\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Store\Model\StoreManagerInterface;

class Status extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * Queue row statuses - keep in sync with
     * DamConsultants\Ahfproducts\Cron\UpdateAllSku::SKU_STATUS_*
     */
    const STATUS_PENDING  = 'pending';
    const STATUS_NO_DATA  = 'no_data';
    const STATUS_FAILED   = 'failed';
    const STATUS_COMPLETE = 'complete';

    /**
     * Label + colour for every known status.
     *
     * @var array
     */
    protected $statusMap = [
        self::STATUS_PENDING => [
            'label'      => 'Pending',
            'color'      => '#eb5202',
            'background' => '#feeee1',
            'border'     => '#ed4f2e'
        ],
        self::STATUS_NO_DATA => [
            'label'      => 'No Data',
            'color'      => '#5f5f5f',
            'background' => '#f5f5f5',
            'border'     => '#adadad'
        ],
        self::STATUS_FAILED => [
            'label'      => 'Failed',
            'color'      => '#e02b27',
            'background' => '#fae5e5',
            'border'     => '#e02b27'
        ],
        self::STATUS_COMPLETE => [
            'label'      => 'Complete',
            'color'      => '#5b8116',
            'background' => '#d0e5a9',
            'border'     => '#5b8116'
        ],
    ];

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Closed constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param StoreManagerInterface $storeManager
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * PrepareDataSource
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            if (!is_array($item) || !array_key_exists($fieldName, $item)) {
                continue;
            }

            $item[$fieldName] = $this->renderStatus($item[$fieldName]);
        }

        return $dataSource;
    }

    /**
     * Build the coloured badge for a single status value.
     *
     * @param string|null $status
     * @return string
     */
    protected function renderStatus($status)
    {
        $key = strtolower(trim((string)$status));

        if (isset($this->statusMap[$key])) {
            $style = $this->statusMap[$key];
            $label = __($style['label']);
        } else {
            // Unknown / empty value: show it as-is rather than mislabelling it
            // "Pending". Escaped, because it comes straight from the database.
            $style = [
                'color'      => '#5f5f5f',
                'background' => '#f5f5f5',
                'border'     => '#adadad'
            ];
            $label = $key === ''
                ? __('Unknown')
                : htmlspecialchars(ucwords(str_replace('_', ' ', $key)), ENT_QUOTES, 'UTF-8');
        }

        return '<span style="color:' . $style['color'] . ';'
            . ' font-weight:bold;'
            . ' background:' . $style['background'] . ' none repeat scroll 0 0;'
            . ' border:' . $style['border'] . ' 1px solid;'
            . ' display:block; line-height:19px;'
            . ' padding:0 5px; text-align:center;'
            . ' text-transform:uppercase;">'
            . $label
            . '</span>';
    }
}