<?php

declare(strict_types=1);

namespace DamConsultants\Ahfproducts\Plugin\App\Http;

use Magento\Framework\App\Http\Context;
use DamConsultants\Ahfproducts\Model\CustomerContextResolver;

class ContextPlugin
{
    private CustomerContextResolver $contextResolver;

    public function __construct(CustomerContextResolver $contextResolver)
    {
        $this->contextResolver = $contextResolver;
    }

    /**
     * Vary cache by AHF customer numbers.
     *
     * @param Context $subject
     * @return void
     */
    public function beforeGetVaryString(Context $subject)
    {
        $data = $this->contextResolver->getCustomerData();
        if ($data && !empty($data['customer_numbers'])) {
            $subject->setValue(
                'ahf_customer_numbers',
                implode(',', $data['customer_numbers']),
                ''
            );
        }
    }
}
