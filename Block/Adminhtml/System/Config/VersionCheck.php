<?php

namespace DamConsultants\Ahfproducts\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;

class VersionCheck extends Field
{
    protected $curl;
    protected $scopeConfig;
    protected $moduleList;

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('DamConsultants_Ahfproducts::system/config/version_message.phtml');
    }

    public function __construct(
        Curl $curl,
        ScopeConfigInterface $scopeConfig,
        \Magento\Backend\Block\Template\Context $context,
        ModuleListInterface $moduleList,
        array $data = []
    ) {
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->moduleList = $moduleList; 
        parent::__construct($context, $data);
    }

    public function getLatestVersion()
    {
        $repoUrl = 'https://api.github.com/repos/damconsultants/ahfproducts/releases/latest';
        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->curl->setOption(CURLOPT_USERAGENT, 'Magento2');
        $this->curl->get($repoUrl);
       
        $response = $this->curl->getBody();
        
        if ($response) {
            $data = json_decode($response, true);
            
            return isset($data['tag_name']) ? ltrim($data['tag_name'], 'v') : null;
        }
        return null;
    }

    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    public function getCurrentVersion()
    {
        $moduleInfo = $this->moduleList->getOne('DamConsultants_Ahfproducts');
        return $moduleInfo['setup_version'] ?? 'N/A';
    }

    public function isUpdateAvailable()
    {
        $latest = $this->getLatestVersion();
       
        $current = $this->getCurrentVersion();
        return $latest && $current && version_compare($current, $latest, '<');
    }

    public function getChangeLogUrl()
    {
        return 'https://github.com/damconsultants/ahfproducts/releases';
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->toHtml();
    }
}
