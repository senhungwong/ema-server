<?php

namespace App\Services;

use App\Models\Neo\Transaction;
use GraphAware\Neo4j\OGM\EntityManager;

class TransactionService
{
    /**
     * Search support for:
     *
     * D: Mon through Sun
     * l: Sunday through Saturday
     * F: January through December
     * M: Jan through Dec
     * Y: Examples: 1999 or 2003
     * m: 01 through 12
     * d: 01 to 31
     * a: am or pm
     * A: AM or PM
     * H: 00 through 23
     * i: 00 to 59
     * s: 00 through 59
     */
    private const DATE_SEARCH_FORMAT = 'D l F M YYYY mm dd a A HH:ii:ss';

    private const TRANSACTION_SEARCH_DELIMITER = ",";

    /** @var EntityManager $entityManager */
    private $entityManager;

    /**
     * TransactionService constructor.
     *
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param int $userId
     *
     * @return array|mixed
     */
    public function getAllTransactions(int $userId)
    {
        $query = "
            MATCH (:User {sqlId: {sqlId}})-[:HAS_TRANSACTION]->(t:Transaction)
            RETURN DISTINCT t
        ";

        return $this->entityManager->createQuery($query)
            ->setParameter('sqlId', $userId)
            ->addEntityMapping('t', Transaction::class)
            ->getResult();
    }

    /**
     * @param array $transactions
     * @param string $fragmentString
     *
     * @return array
     */
    public function filterTransactionsWithFragments(array $transactions, string $fragmentString): array
    {
        $fragments = explode(self::TRANSACTION_SEARCH_DELIMITER, $fragmentString);

        foreach ($fragments as $fragment) {
            $transactions = array_filter($transactions, function ($transaction) use ($fragment) {
                return $this->isTransactionMatchFilter($transaction, trim($fragment));
            });
        }

        return $transactions;
    }

    /**
     * @param int $userId
     * @param float $amount
     * @param string $description
     * @param int $timestamp
     *
     * @return mixed
     */
    public function createTransaction(int $userId, float $amount, string $description, int $timestamp)
    {
        $query = "
            MATCH (u:User {sqlId: {userId}})
            CREATE (t:Transaction {amount: {amount}, description: {description}, timestamp: {timestamp}})
            CREATE (u)-[:HAS_TRANSACTION]->(t)
            RETURN t
        ";

        return $this->entityManager->createQuery($query)
            ->setParameter('userId', $userId)
            ->setParameter('amount', $amount)
            ->setParameter('description', $description)
            ->setParameter('timestamp', $timestamp)
            ->addEntityMapping('t', Transaction::class)
            ->getOneResult();
    }

    /**
     * @param int $userId
     * @param int $id
     * @param float $amount
     * @param string $description
     * @param int $timestamp
     *
     * @return mixed
     */
    public function updateTransactionById(int $userId, int $id, float $amount, string $description, ?int $timestamp)
    {
        $query = "
            MATCH (u:User {sqlId: {uid}})
            MATCH (u)-[:HAS_TRANSACTION]->(t:Transaction)
            WHERE ID(t) = {id}
            SET t.amount = {amount}
        ";

        if ($timestamp) {
            $query .= "
                SET t.timestamp = $timestamp
            ";
        }

        $query .= "
            RETURN t
        ";

        return $this->entityManager->createQuery($query)
            ->setParameter('uid', $userId)
            ->setParameter('id', $id)
            ->setParameter('amount', $amount)
            ->setParameter('description', $description)
            ->addEntityMapping('t', Transaction::class)
            ->getOneResult();
    }

    /**
     * @param int $userId
     * @param int $id
     *
     * @return mixed
     */
    public function getUserTransactionById(int $userId, int $id)
    {
        $query = "
            MATCH (u:User {sqlId: {uid}})
            MATCH (u)-[:HAS_TRANSACTION]->(t:Transaction)
            WHERE ID(t) = {id}
            RETURN t
        ";

        return $this->entityManager->createQuery($query)
            ->setParameter('uid', $userId)
            ->setParameter('id', $id)
            ->addEntityMapping('t', Transaction::class)
            ->getOneResult();
    }

    /**
     * @param int $userId
     * @param int $id
     *
     * @return array|mixed
     * @throws \Exception
     */
    public function deleteTransactionById(int $userId, int $id)
    {
        $query = "
            MATCH (u:User {sqlId: {uid}})
            MATCH (u)-[:HAS_TRANSACTION]->(t:Transaction)
            WHERE ID(t) = {id}
            DETACH DELETE t
        ";

        return $this->entityManager->createQuery($query)
            ->setParameter('uid', $userId)
            ->setParameter('id', $id)
            ->addEntityMapping('t', Transaction::class)
            ->execute();
    }

    /**
     * @param Transaction $transaction
     * @param string $fragment
     *
     * @return bool
     */
    private function isTransactionMatchFilter(Transaction $transaction, string $fragment): bool
    {
        $res = true;

        /* Handle negation */
        if ($fragment[0] == '!') {
            $res = false;
            $fragment = substr($fragment, 1);
        }

        /* Amount comparison */
        if (is_numeric($fragment) && $transaction->getAmount() == (float)$fragment) {
            return $res;
        }

        if ($fragment[0] === '=' && $transaction->getAmount() == (float)substr($fragment, 1)) {
            return $res;
        }

        $twoCharOperator = $fragment[0] . $fragment[1];

        if ($twoCharOperator === '>=') {
            $amount = substr($fragment, 2);

            if (is_numeric($amount) && $transaction->getAmount() >= (float)$amount) {
                return $res;
            }
        }

        if ($twoCharOperator === '<=') {
            $amount = substr($fragment, 2);

            if (is_numeric($amount) && $transaction->getAmount() <= (float)$amount) {
                return $res;
            }
        }

        if ($fragment[0] === '>') {
            $amount = substr($fragment, 1);

            if (is_numeric($amount) && $transaction->getAmount() > (float)$amount) {
                return $res;
            }
        }

        if ($fragment[0] === '<') {
            $amount = substr($fragment, 1);

            if (is_numeric($amount) && $transaction->getAmount() < (float)$amount) {
                return $res;
            }
        }

        if ($fragment[0] === '<' && $transaction->getAmount() < (float)substr($fragment, 1)) {
            return $res;
        }

        /* Check if description contains the word */
        if (strpos($transaction->getDescription(), $fragment) !== false) {
            return $res;
        }

        /* Check if date cointains the word */
        $dateFilterString = strtolower(date(self::DATE_SEARCH_FORMAT, $transaction->getTimestamp()));

        if (strpos($dateFilterString, strtolower($fragment)) !== false) {
            return $res;
        }

        return !$res;
    }
}
