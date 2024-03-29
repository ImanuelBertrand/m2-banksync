<?php

namespace Ibertrand\BankSync\Helper;

use Exception;
use Ibertrand\BankSync\Model\TempTransaction;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;

class Matching extends AbstractHelper
{

    protected CustomerResource $customerResource;
    protected CustomerFactory $customerFactory;
    private Config $config;

    const SPECIAL_CHARACTERS = [
        'ae' => '(?:[aáà]e|ä|æ)',
        'oe' => '(?:[oóò]e|ö|œ)',
        'ue' => '(?:[uúù]e|ü)',
        'ä' => '(?:ae|ä)',
        'ö' => '(?:oe|ö)',
        'ü' => '(?:ue|ü)',
        'ß' => '(?:ss|ß)',
        'ss' => '(?:ss|ß)',

        'a' => '(?:a|á|à)',
        'e' => '(?:e|é|è)',
        'i' => '(?:i|í|ì)',
        'o' => '(?:o|ó|ò)',
        'u' => '(?:u|ú|ù)',
        'n' => '(?:n|ñ)',

        'ñ' => '(?:n|ñ)',
        'ç' => '(?:c|ç)',
        'á' => '(?:a|á)',
        'à' => '(?:a|à)',
        'ó' => '(?:o|ó)',
        'ò' => '(?:o|ò)',
        'é' => '(?:e|é)',
        'è' => '(?:e|è)',
        'ú' => '(?:u|ú)',
        'ù' => '(?:u|ù)',

    ];

    public function __construct(
        Context          $context,
        CustomerFactory  $customerFactory,
        CustomerResource $customerResource,
        Config           $config,
    ) {
        $this->customerFactory = $customerFactory;
        $this->customerResource = $customerResource;
        $this->config = $config;

        parent::__construct($context);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function normalizeName(string $name): string
    {
        $name = mb_strtolower($name);
        $name = preg_replace('/\s+/u', ' ', $name);
        return trim($name);
    }

    /**
     * @param array        $nameScores
     * @param array        $halfScoreKeys
     * @param Address|null $address
     * @return void
     */
    protected function addAddressScores(array &$nameScores, array &$halfScoreKeys, ?Order\Address $address): void
    {
        if (!$address) {
            return;
        }
        $nameScores = array_merge($nameScores, [
            trim(($address->getFirstname() ?? '') . ' ' . ($address->getLastname() ?? '')) => 1,
            trim(($address->getCompany() ?? '')) => 1,
        ]);
        $halfScoreKeys = array_merge($halfScoreKeys, [
            trim($address->getFirstname() ?? ""),
            trim($address->getLastname() ?? ""),
        ]);
        unset($nameScores['']);
        unset($halfScoreKeys['']);
    }

    /**
     * @param Order $order
     * @return float[]
     */
    protected function getNameComparisonScores(Order $order): array
    {
        $nameScores = [
            trim(($order->getCustomerName() ?? '')) => 1,
            trim(($order->getCustomerFirstname() ?? '') . ' ' . ($order->getCustomerLastname() ?? '')) => 1,
            trim(($order->getCustomerLastname() ?? '') . ' ' . ($order->getCustomerFirstname() ?? '')) => 1,
        ];

        $halfScoreKeys = [
            trim(($order->getCustomerFirstname() ?? '')),
            trim(($order->getCustomerLastname() ?? '')),
        ];

        $this->addAddressScores($nameScores, $halfScoreKeys, $order->getBillingAddress());
        $this->addAddressScores($nameScores, $halfScoreKeys, $order->getShippingAddress());

        foreach ($halfScoreKeys as $key) {
            // Only set the score to 0.5 if it's not already set (i.e., it's not in the $nameScores array)
            if (!isset($nameScores[$key])) {
                $nameScores[$key] = 0.5;
            }
        }
        arsort($nameScores, SORT_NUMERIC);
        return $nameScores;
    }

    /**
     * @param TempTransaction    $tempTransaction
     * @param Invoice|Creditmemo $document
     * @return array
     */
    public function getNameMatches(TempTransaction $tempTransaction, Invoice|Creditmemo $document): array
    {
        $transactionName = $tempTransaction->getPayerName();
        $transactionNames = [$transactionName];
        $fixedTransactionName = "";
        if (str_contains($transactionName, ',')) {
            $parts = preg_split('/\s*,\s*/', $transactionName);
            if (count($parts) == 2) {
                $fixedTransactionName = $parts[1] . ' ' . $parts[0];
            }
        }
        if ($fixedTransactionName) {
            $transactionNames[] = $fixedTransactionName;
        }

        array_walk($transactionNames, function (&$name) {
            $name = $this->normalizeName($name);
        });

        $nameScores = $this->getNameComparisonScores($document->getOrder());
        $matches = [];
        foreach ($nameScores as $name => $score) {
            $name = $this->normalizeName($name);
            if (empty($name)) {
                continue;
            }
            foreach ($transactionNames as $transactionName) {
                $pattern = $this->getMatchPattern($name);
                if (preg_match($pattern, $transactionName)) {
                    $matches[$name] = $score;
                }
            }
        }
        return $matches;
    }

    /**
     * Aggregate scores using a dynamically weighted sum
     *
     * @param float[] $scores The array of scores, each in [0, 1].
     * @return float The aggregated result, in [0, 1].
     */
    protected function aggregateScores(array $scores): float
    {
        if (empty($scores)) {
            return 0.0;
        }
        rsort($scores);
        $result = 0;
        foreach ($scores as $value) {
            if ($value > 1) {
                $this->_logger->warning("Match value is greater than 1: $value");
                $value = 1;
            }
            if ($value <= 0 || $result >= 1) {
                // exit early if the calculation is done
                break;
            }
            // The result is the sum of the scores, but the score is multiplied by (1 - the current result)
            // This means all matches are aggregated while still returning a value between 0 and 1.
            $result += $value * (1 - $result);
        }
        return $result;
    }

    /**
     * @param TempTransaction    $tempTransaction
     * @param Invoice|Creditmemo $document
     *
     * @return float
     */
    protected function compareName(TempTransaction $tempTransaction, Invoice|Creditmemo $document): float
    {
        return $this->aggregateScores($this->getNameMatches($tempTransaction, $document));
    }

    public function getIncrementIdPattern(string $type, string $incrementId): string
    {
        $template = $this->config->getMatchingPattern($type);
        $pattern = str_replace('{{value}}', preg_quote($incrementId), $template);

        if (preg_match($pattern, '') === false) {
            $this->_logger->error("Invalid pattern for $type increment ID: $pattern");
            return "/$ not match possible ^/";
        }
        return $pattern;
    }

    /**
     * Adds a score to the matches array.
     * It makes sure the score is only added if it's greater than the current score for the key.
     *
     * @param array  $matches The array of matches.
     * @param string $key The key of the match to add the score to.
     * @param float  $score The score to be added.
     * @return array The updated array of matches with the added score.
     */
    protected function addScore(array $matches, string $key, float $score): array
    {
        if ($score > ($matches[$key] ?? 0)) {
            $matches[$key] = $score;
        }
        return $matches;
    }

    /**
     * @param string $searchString
     * @return string
     */
    public function getMatchPattern(string $searchString): string
    {
        $pattern = preg_quote($searchString, '/');
        $pattern = str_replace(
            array_keys(self::SPECIAL_CHARACTERS),
            array_values(self::SPECIAL_CHARACTERS),
            $pattern
        );
        return '/\b' . $pattern . '\b/iu';
    }

    /**
     * @param TempTransaction    $tempTransaction
     * @param Invoice|Creditmemo $document
     * @return array
     * @throws Exception
     */
    public function getPurposeMatches(TempTransaction $tempTransaction, Invoice|Creditmemo $document): array
    {
        $purpose = trim($tempTransaction->getPurpose() ?? "");
        if (empty($purpose)) {
            return [];
        }

        $results = [];

        $documentIncrementId = $document->getIncrementId();
        $pattern = $this->getIncrementIdPattern("document", $documentIncrementId);
        if (preg_match($pattern, $purpose)) {
            $results = $this->addScore($results, $documentIncrementId, 1);
        }

        $orderIncrementId = $document->getOrder()->getIncrementId();
        $pattern = $this->getIncrementIdPattern("order", $orderIncrementId);
        if (preg_match($pattern, $purpose) && !isset($results[$orderIncrementId])) {
            $results = $this->addScore($results, $orderIncrementId, 0.5);
        }

        $nameScores = $this->getNameComparisonScores($document->getOrder());

        foreach ($nameScores as $text => $score) {
            $textNormalized = $this->normalizeName($text);
            if (empty($textNormalized)) {
                continue;
            }
            $pattern = $this->getMatchPattern($textNormalized);
            try {
                if (preg_match($pattern, $purpose)) {
                    $results = $this->addScore($results, $text, $score / 2);
                }
            } catch (Exception $e) {
                $this->_logger->error("Error matching '$text' with pattern '$pattern'");
                throw $e;
            }
        }

        if ($document->getOrder()->getCustomerId()) {
            $customer = $this->loadCustomer($document->getOrder()->getCustomerId());
            /** @noinspection PhpUndefinedMethodInspection */
            $customerIncrementId = $customer->getIncrementId();
            if (!empty($customerIncrementId)) {
                $pattern = $this->getIncrementIdPattern("customer", $customerIncrementId);
                if (preg_match($pattern, $purpose)) {
                    $results = $this->addScore($results, $customerIncrementId, 0.5);
                }
                $pattern = $this->getIncrementIdPattern("customer", trim($customerIncrementId, '0'));
                if (preg_match($pattern, $purpose)) {
                    $results = $this->addScore($results, trim($customerIncrementId, '0'), 0.25);
                }
            }
        }

        return $results;
    }

    /**
     * Returns an aggregated score for the purpose:
     * 1 if the purpose contains the document IncrementId,
     * 0.5 if the purpose contains the order IncrementId,
     * 0.5 if the purpose contains the customer IncrementId,
     * 0.25 if the purpose contains the customer IncrementId without leading zeros,
     * 0 otherwise.
     *
     * The weighted aggregation makes sure the resulting score is between 0 and 1.
     *
     * @param TempTransaction    $tempTransaction
     * @param Invoice|Creditmemo $document
     *
     * @return float
     * @throws Exception
     */
    protected function comparePurpose(TempTransaction $tempTransaction, Invoice|Creditmemo $document): float
    {
        return $this->aggregateScores($this->getPurposeMatches($tempTransaction, $document));
    }

    /**
     * @param TempTransaction    $tempTransaction
     * @param Invoice|Creditmemo $document
     *
     * @return float
     * @throws Exception
     */
    public function getMatchConfidence(TempTransaction $tempTransaction, Invoice|Creditmemo $document): float
    {
        $weightAmount = $this->config->getWeightConfig('amount');
        $weightPurpose = $this->config->getWeightConfig('purpose');
        $weightName = $this->config->getWeightConfig('payer_name');

        $amountDif = abs(abs($tempTransaction->getAmount()) - $document->getGrandTotal());

        $amountScore = $this->config->useStrictAmountMatching()
            ? $amountDif < 0.01 ? $weightAmount : 0
            : $weightAmount * ($amountDif < 0.01 ? 1 : (1 - $amountDif / $this->config->getAmountThreshold()));
        $purposeScore = $weightPurpose * $this->comparePurpose($tempTransaction, $document);
        $nameScore = $weightName * $this->compareName($tempTransaction, $document);

        return round($amountScore + $purposeScore + $nameScore);
    }

    /**
     * @return string[]
     */
    public function getPaymentMethods(): array
    {
        return explode(',', $this->scopeConfig->getValue('banksync/matching/filter/payment_methods') ?? "");
    }

    /**
     * @param int $customerId
     *
     * @return Customer
     */
    private function loadCustomer(int $customerId): Customer
    {
        $customer = $this->customerFactory->create();
        $this->customerResource->load($customer, $customerId);
        return $customer;
    }
}
