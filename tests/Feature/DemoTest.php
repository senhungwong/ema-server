<?php

namespace Tests\Feature;

use GraphAware\Neo4j\OGM\EntityManager;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Class DemoTest
 * @package Tests\Feature
 *
 * NOTE: This is not the right way of writing tests!!!
 * The test is used for generating demo data (and do simple tests on some endpoints).
 */
class DemoTest extends TestCase
{
    private const TEST_USERNAME = 'Alex';
    private const TEST_EMAIL    = 'wongsenw@sfu.ca';
    private const TEST_PASSWORD = 'testpassword';

    /** @var EntityManager $entityManager */
    private $entityManager;
    /** @var array $headers The main demo user headers */
    private $headers = [];
    /** @var int $demoUserId The main demo user id */
    private $demoUserId;

    /**
     * Set up
     */
    protected function setUp()
    {
        parent::setUp();
        Artisan::call('migrate:refresh');
        $this->entityManager = $this->app->make(EntityManager::class);
    }

    /**
     * @test
     *
     * @throws \Exception
     *
     * NOTE: This is not the right way of writing tests!!!
     * The test is used for generating demo data (and do simple tests on some endpoints).
     */
    public function demoTest()
    {
        $this->removeNeoDatabaseContent();

        $this->registerAndLogin();

        $this->createSomeExpenses();

        [$ids, $tokens] = $this->makeUpSomeUsers();

        $this->buildFriendship($ids, $tokens);

        $this->prepareFriendRecommondation($tokens);

        $this->followSomeFriends($ids);
    }

    /**
     * @throws \Exception
     */
    private function removeNeoDatabaseContent()
    {
        $this->entityManager->createQuery("MATCH (n) DETACH DELETE n")->execute();
    }

    /**
     * Register an user and login
     */
    private function registerAndLogin()
    {
        $registerResponse = $this->post('/api/v1/auth/register', [
            'username' => self::TEST_USERNAME,
            'email' => self::TEST_EMAIL,
            'password' => self::TEST_PASSWORD,
        ])->assertStatus(Response::HTTP_CREATED)->json();

        $this->headers['Authorization'] = "Bearer " . $registerResponse['token'];
        $this->demoUserId = $registerResponse['data']['id'];

        $this->post('/api/v1/auth/login', [
            'email' => self::TEST_EMAIL,
            'password' => self::TEST_PASSWORD
        ])->assertStatus(Response::HTTP_OK);
    }

    /**
     * Create some expenses
     */
    private function createSomeExpenses()
    {
        foreach ($this->expenses() as $expense) {
            $this->post('/api/v1/transaction', [
                'amount' => $expense['amount'],
                'description' => $expense['description'],
                'timestamp' => $expense['timestamp'],
                'tags' => $expense['tags'],
            ], $this->headers)->assertStatus(Response::HTTP_CREATED);
        }
    }

    /**
     * @return array
     */
    private function makeUpSomeUsers(): array
    {
        $userIds = [];
        $userTokens = [];

        foreach ($this->users() as $user) {
            $username = $user['username'];

            $registerResponse = $this->post('/api/v1/auth/register', [
                'username' => $username,
                'email' => $user['email'],
                'password' => self::TEST_PASSWORD,
            ])->assertStatus(Response::HTTP_CREATED)->json();

            $userIds[$username] = $registerResponse['data']['id'];
            $userTokens[$username] = $registerResponse['token'];
        }

        return [$userIds, $userTokens];
    }

    /**
     * @param array $userIds
     * @param array $tokens
     */
    private function buildFriendship(array $userIds, array $tokens)
    {
        foreach ($this->users() as $user) {
            $headers = ['Authorization' => 'Bearer ' . $tokens[$user['username']]];

            foreach ($user['followings'] as $following) {
                $followingId = $userIds[$following];
                $this->post('/api/v1/user/followings/' . $followingId, [], $headers)->assertStatus(Response::HTTP_OK);
            }
        }
    }

    /**
     * @param array $tokens
     */
    private function prepareFriendRecommondation(array $tokens)
    {
        $followers = ['David', 'James'];

        foreach ($followers as $follower) {
            $this->post('/api/v1/user/followings/' . $this->demoUserId, [], [
                'Authorization' => 'Bearer ' . $tokens[$follower],
            ])->assertStatus(Response::HTTP_OK);
        }
    }

    /**
     * @param array $ids
     */
    private function followSomeFriends(array $ids)
    {
        $followings = ['David', 'James'];

        foreach ($followings as $following) {
            $this->post('/api/v1/user/followings/' . $ids[$following], [], $this->headers)
                ->assertStatus(Response::HTTP_OK);
        }
    }

    /**
     * @return array
     */
    private function expenses(): array
    {
        return [
            [
                'amount'      => 5,
                'description' => 'Lost in CMPT 470 lecture',
                'timestamp'   => 1535829000,
                'tags'        => 'lost',
            ],
            [
                'amount'      => 13.5,
                'description' => 'Delicious lunch!',
                'timestamp'   => 1538740800,
                'tags'        => 'lunch,healthy',
            ],
            [
                'amount'      => 37.5,
                'description' => 'MSP',
                'timestamp'   => 1537448400,
                'tags'        => '',
            ],
            [
                'amount'      => 3,
                'description' => 'Lost again in CMPT 470 lecture :(',
                'timestamp'   => 1542306600,
                'tags'        => 'lost',
            ],
            [
                'amount'      => 19.99,
                'description' => 'Umm',
                'timestamp'   => 1521742332,
                'tags'        => 'dinner,fast-food',
            ],
            [
                'amount'      => 22.9,
                'description' => 'Would not go to this restaurant again',
                'timestamp'   => 1526841240,
                'tags'        => 'dinner,wasted',
            ],
        ];
    }

    /**
     * @return array
     */
    private function users(): array
    {
        return [
            [
                'username'   => 'David',
                'email'      => 'ycchou@sfu.ca',
                'followings' => ['James', 'Jenny', 'RayH', 'Branton'],
            ],
            [
                'username'   => 'James',
                'email'      => 'sxa16@sfu.ca',
                'followings' => ['David', 'Jenny', 'RayH', 'Branton'],
            ],
            [
                'username'   => 'Jenny',
                'email'      => 'jianings@sfu.ca',
                'followings' => ['David', 'James', 'RayH', 'Branton'],
            ],
            [
                'username'   => 'RayH',
                'email'      => 'yuhuih@sfu.ca',
                'followings' => ['David', 'James', 'Jenny', 'Branton'],
            ],
            [
                'username'   => 'Branton',
                'email'      => 'brantonl@sfu.ca',
                'followings' => ['David', 'James', 'Jenny', 'RayH'],
            ],
        ];
    }
}
