<?php

declare(strict_types=1);

namespace DamConsultants\Ahfproducts\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Company\Api\CompanyManagementInterface;
use Psr\Log\LoggerInterface;

class CustomerContextResolver
{
    private CustomerSession $customerSession;
    private CustomerRepositoryInterface $customerRepository;
    private CompanyManagementInterface $companyManagement;
    private LoggerInterface $logger;

    public function __construct(
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        CompanyManagementInterface $companyManagement,
        LoggerInterface $logger
    ) {
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->companyManagement = $companyManagement;
        $this->logger = $logger;
    }

    /**
     * Get current customer context.
     *
     * @return array|null
     */
    public function getCustomerData(): ?array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return null;
        }

        try {
            $customerId = (int)$this->customerSession->getCustomerId();

            if ($customerId <= 0) {
                return null;
            }

            $customer = $this->customerRepository->getById($customerId);

            $customerData = [
                'customer_id' => $customerId,
                'customer_email' => (string)$customer->getEmail(),
                'customer_numbers' => []
            ];

            $customerNumberAttribute = $customer->getCustomAttribute(
                'customer_number'
            );

            if ($customerNumberAttribute) {
                $customerNumber = trim(
                    (string)$customerNumberAttribute->getValue()
                );

                if ($customerNumber !== '') {
                    $customerData['customer_numbers'][] = $customerNumber;
                }
            }

            try {
                $company = $this->companyManagement->getByCustomerId(
                    $customerId
                );
            } catch (\Throwable $exception) {
                $company = null;
            }

            if ($company) {
                $customerData['company_id'] = (int)$company->getId();

                $companyCustomerNumber = trim(
                    (string)$company->getData('customer_number')
                );

                if ($companyCustomerNumber !== '') {
                    $customerData['customer_numbers'][] =
                        $companyCustomerNumber;
                }
            }

            $customerData['customer_numbers'] = array_values(
                array_unique(
                    array_filter(
                        $customerData['customer_numbers']
                    )
                )
            );

            return $customerData;
        } catch (\Throwable $exception) {
            $this->logger->error(
                'Unable to build AHF customer context.',
                [
                    'exception' => $exception
                ]
            );

            return null;
        }
    }
}