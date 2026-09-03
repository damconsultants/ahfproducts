<?php
declare(strict_types=1);

namespace DamConsultants\Ahfproducts\Plugin\App\Action;

use DamConsultants\Ahfproducts\Model\CustomerContextResolver;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\RequestInterface;

class CustomerContextPlugin
{
    public const CONTEXT_AHF_NUMBERS = 'ahf_customer_numbers';

    private HttpContext $httpContext;
    private CustomerContextResolver $resolver;

    public function __construct(
        HttpContext $httpContext,
        CustomerContextResolver $resolver
    ) {
        $this->httpContext = $httpContext;
        $this->resolver = $resolver;
    }

    public function beforeExecute(ActionInterface $subject)
    {
        $data = $this->resolver->getCustomerData();
        $numbers = $data['customer_numbers'] ?? [];

        $this->httpContext->setValue(
            self::CONTEXT_AHF_NUMBERS,
            $numbers ? implode(',', $numbers) : '',
            ''
        );

        return null;
    }
}