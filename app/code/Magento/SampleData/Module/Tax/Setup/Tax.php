<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\SampleData\Module\Tax\Setup;

use Magento\SampleData\Helper\Csv\ReaderFactory as CsvReaderFactory;
use Magento\SampleData\Helper\Fixture as FixtureHelper;
use Magento\SampleData\Model\SetupInterface;

/**
 * Class Tax
 */
class Tax implements SetupInterface
{
    /**
     * @var \Magento\Tax\Api\TaxRuleRepositoryInterface
     */
    protected $taxRuleRepository;

    /**
     * @var \Magento\Tax\Api\Data\TaxRuleInterfaceFactory
     */
    protected $ruleFactory;

    /**
     * @var \Magento\Tax\Api\TaxRateRepositoryInterface
     */
    protected $taxRateRepository;

    /**
     * @var \Magento\Tax\Api\Data\TaxRateInterfaceFactory
     */
    protected $rateFactory;

    /**
     * @var \Magento\Tax\Model\Calculation\RateFactory
     */
    protected $taxRateFactory;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $criteriaBuilder;

    /**
     * @var \Magento\Framework\Api\FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var \Magento\SampleData\Helper\Fixture
     */
    protected $fixtureHelper;

    /**
     * @var \Magento\SampleData\Helper\Csv\ReaderFactory
     */
    protected $csvReaderFactory;

    /**
     * @var \Magento\SampleData\Model\Logger
     */
    protected $logger;

    /**
     * @param \Magento\Tax\Api\TaxRuleRepositoryInterface $taxRuleRepository
     * @param \Magento\Tax\Api\Data\TaxRuleInterfaceFactory $ruleFactory
     * @param \Magento\Tax\Api\TaxRateRepositoryInterface $taxRateRepository
     * @param \Magento\Tax\Api\Data\TaxRateInterfaceFactory $rateFactory
     * @param \Magento\Tax\Model\Calculation\RateFactory $taxRateFactory
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $criteriaBuilder
     * @param \Magento\Framework\Api\FilterBuilder $filterBuilder
     * @param FixtureHelper $fixtureHelper
     * @param CsvReaderFactory $csvReaderFactory
     * @param \Magento\SampleData\Model\Logger $logger
     */
    public function __construct(
        \Magento\Tax\Api\TaxRuleRepositoryInterface $taxRuleRepository,
        \Magento\Tax\Api\Data\TaxRuleInterfaceFactory $ruleFactory,
        \Magento\Tax\Api\TaxRateRepositoryInterface $taxRateRepository,
        \Magento\Tax\Api\Data\TaxRateInterfaceFactory $rateFactory,
        \Magento\Tax\Model\Calculation\RateFactory $taxRateFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $criteriaBuilder,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        FixtureHelper $fixtureHelper,
        CsvReaderFactory $csvReaderFactory,
        \Magento\SampleData\Model\Logger $logger
    ) {
        $this->taxRuleRepository = $taxRuleRepository;
        $this->ruleFactory = $ruleFactory;
        $this->taxRateRepository = $taxRateRepository;
        $this->rateFactory = $rateFactory;
        $this->taxRateFactory = $taxRateFactory;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->fixtureHelper = $fixtureHelper;
        $this->csvReaderFactory = $csvReaderFactory;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $this->logger->log('Installing taxes:');
        $fixtureFile = 'Tax/tax_rate.csv';
        $fixtureFilePath = $this->fixtureHelper->getPath($fixtureFile);
        /** @var \Magento\SampleData\Helper\Csv\Reader $csvReader */
        $csvReader = $this->csvReaderFactory->create(['fileName' => $fixtureFilePath, 'mode' => 'r']);
        foreach ($csvReader as $data) {
            if ($this->rateFactory->create()->loadByCode($data['code'])->getId()) {
                continue;
            }
            $taxRate = $this->rateFactory->create();
            $taxRate->setCode($data['code'])
                ->setTaxCountryId($data['tax_country_id'])
                ->setTaxRegionId($data['tax_region_id'])
                ->setTaxPostcode($data['tax_postcode'])
                ->setRate($data['rate']);
            $this->taxRateRepository->save($taxRate);
            $this->logger->logInline('.');
        }

        $fixtureFile = 'Tax/tax_rule.csv';
        $fixtureFilePath = $this->fixtureHelper->getPath($fixtureFile);
        /** @var \Magento\SampleData\Helper\Csv\Reader $csvReader */
        $csvReader = $this->csvReaderFactory->create(['fileName' => $fixtureFilePath, 'mode' => 'r']);
        foreach ($csvReader as $data) {
            $filter = $this->filterBuilder->setField('code')
                ->setConditionType('=')
                ->setValue($data['code'])
                ->create();
            $criteria = $this->criteriaBuilder->addFilters([$filter])->create();
            $existingRates = $this->taxRuleRepository->getList($criteria)->getItems();
            if (!empty($existingRates)) {
                continue;
            }

            $taxRate = $this->taxRateFactory->create()->loadByCode($data['tax_rate']);
            $taxRule = $this->ruleFactory->create();
            $taxRule->setCode($data['code'])
                ->setTaxRateIds([$taxRate->getId()])
                ->setCustomerTaxClassIds([$data['tax_customer_class']])
                ->setProductTaxClassIds([$data['tax_product_class']])
                ->setPriority($data['priority'])
                ->setCalculateSubtotal($data['calculate_subtotal'])
                ->setPosition($data['position']);
            $this->taxRuleRepository->save($taxRule);
            $this->logger->logInline('.');
        }
    }
}
