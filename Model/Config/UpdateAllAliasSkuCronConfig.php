<?php

namespace DamConsultants\Ahfproducts\Model\Config;

class UpdateAllAliasSkuCronConfig extends \Magento\Framework\App\Config\Value
{
    /**
     * @var \Magento\Framework\App\Config\ValueFactory
     */
    protected $_configValueFactory;
    /**
     * @var string
     */
    protected $_runModelPath = '';

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Config\ValueFactory $configValueFactory,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        $runModelPath = '',
        array $data = []
    ) {
        $this->_runModelPath = $runModelPath;
        $this->_configValueFactory = $configValueFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function afterSave()
    {
        $time = $this->getData('groups/update_all_aliassku/fields/update_alias_sku_time/value');
        $frequency = $this->getData('groups/update_all_aliassku/fields/update_alias_sku_frequency/value');
        $custom_time = $this->getData('groups/update_all_aliassku/fields/your_min_update_alias_sku_frequency/value');
        $every_min = \DamConsultants\Ahfproducts\Model\Config\Source\Frequency::EVERY_TEN_TIME;
        if ($frequency == $every_min) {
            $cronExprArray = [
                '*/'.$custom_time,
                '*',
                '*',
                '*',
                '*',
            ];
        } else {
            $cronExprArray = [
                (int)$time[1],
                (int)$time[0],
                $frequency == \DamConsultants\Ahfproducts\Model\Config\Source\Frequency::CRON_MONTHLY ? '1' : '*',
                '*',
                $frequency == \DamConsultants\Ahfproducts\Model\Config\Source\Frequency::CRON_WEEKLY ? '1' : '*',
            ];
        }
        $cronExprString = join(' ', $cronExprArray);
        try {
            $this->_configValueFactory->create()->load(
                'crontab/default/jobs/damConsultants_bynder_update_new_alias_sku/schedule/cron_expr',
                'path'
            )->setValue(
                $cronExprString
            )->setPath(
                'crontab/default/jobs/damConsultants_bynder_update_new_alias_sku/schedule/cron_expr'
            )->save();
            $this->_configValueFactory->create()->load(
                'crontab/default/jobs/damConsultants_bynder_update_new_alias_sku/run/model',
                'path'
            )->setValue(
                $this->_runModelPath
            )->setPath(
                'crontab/default/jobs/damConsultants_bynder_update_new_alias_sku/run/model'
            )->save();
        } catch (\Exception $e) {
            $this->messageManager->addException($e, __('We can\'t save the cron expression.'));
        }
        return parent::afterSave();
    }

    public function getConfigValue()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $scopeConfig = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
        return $scopeConfig->getValue(
            'cronimageconfig/update_all_aliassku/your_min_update_alias_sku_frequency',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeManager->getStore()->getStoreId()
        );
    }
}
