<?php 

namespace Shep517\OAuth2\Client\Test\Provider;

use League\OAuth2\Client\Tool\QueryBuilderTrait;
use Mockery as m;

class JobberTest extends \PHPUnit_Framework_TestCase
{
    use QueryBuilderTrait;

    protected $provider;

    protected function setUp()
    {
        $this->provider = new \Shep517\OAuth2\Client\Provider\Jobber([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
        ]);
    }

    protected function getJsonFile($file, $encode = false)
    {
        $json = file_get_contents(dirname(dirname(dirname(__FILE__))).'/'.$file);
        $data = json_decode($json, true);

        if ($encode && json_last_error() == JSON_ERROR_NONE) {
            return $data;
        }

        return $json;
    }

    public function tearDown()
    {
        m::close();
        parent::tearDown();
    }

    public function testAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertNotNull($this->provider->getState());
    }

    public function testGetAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);

        $this->assertEquals('login.jobber.com', $uri['host']);
        $this->assertEquals('oauth2/authorize', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl()
    {
        $params = [];

        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);

        $this->assertEquals('login.jobber.com', $uri['host']);
        $this->assertEquals('/oauth2/token', $uri['path']);
    }

    public function testGetAccessToken()
    {
        $accessToken = $this->getJsonFile('access_token_response.json');
        $response = m::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn($accessToken);
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertNull($token->getExpires());
        $this->assertNull($token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    public function testUserData()
    {
        $accessToken = $this->getJsonFile('access_token_response.json');
        $userJson = $this->getJsonFile('user_response.json');
        $userData = json_decode($userJson, true);

        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn($accessToken);
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);

        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn($userJson);
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);

        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $this->provider->getResourceOwner($token);

        $this->assertEquals($userData, $user->toArray());
        $this->assertEquals($userData['account_id'], $user->getAccountId());
        $this->assertEquals($userData['account_name'], $user->getAccountName());
        $this->assertEquals($userData['login_id'], $user->getId());
        $this->assertEquals($userData['email'], $user->getEmail());
    }

    /**
     * @expectedException League\OAuth2\Client\Provider\Exception\IdentityProviderException
     **/
    public function testExceptionThrownWhenErrorObjectReceived()
    {
        $status = rand(401,599);
        $error = $this->getJsonFile('error_response.json');
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn($error);
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);
        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(1)
            ->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }
}
