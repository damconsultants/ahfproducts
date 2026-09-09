<?php

namespace DamConsultants\Ahfproducts\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class UpdateSkuReportDownload extends Column
{
    private $urlBuilder;

    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $token = (string)($item['token'] ?? '');
                if ($token !== '' && ($item['status'] ?? '') === 'complete') {
                    $item[$this->getData('name')] = [
                        'view' => [
                            'href' => $this->urlBuilder->getUrl(
                                'bynder/index/downloadupdateskureport',
                                ['token' => $token]
                            ),
                            'label' => __('Download CSV'),
                            'target' => '_blank'
                        ]
                    ];
                }
            }
        }
        return $dataSource;
    }
}
