<?php

namespace Pantheon\Terminus\UnitTests\Request;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Psr7\Request as HttpRequest;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use League\Container\Container;
use Pantheon\Terminus\Config\TerminusConfig;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\Terminus\Helpers\LocalMachineHelper;
use Pantheon\Terminus\Models\Workflow;
use Pantheon\Terminus\Request\Request;
use Pantheon\Terminus\Session\Session;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class RequestTest
 * Testing class for Pantheon\Terminus\Request\Request
 * @package Pantheon\Terminus\UnitTests\Request
 */
class RequestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    protected $client;
    /**
     * @var TerminusConfig
     */
    protected $config;
    /**
     * @var Container
     */
    protected $container;
    /**
     * @var Filesystem
     */
    protected $filesystem;
    /**
     * @var HttpRequest
     */
    protected $http_request;
    /**
     * @var LocalMachineHelper
     */
    protected $local_machine_helper;
    /**
     * @var Request
     */
    protected $request;
    /**
     * @var Session
     */
    protected $session;
    /**
     * @var string
     */
    protected $user_agent;

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        parent::setUp();

        $this->request = new Request();
        $this->http_request = $this->getMockBuilder(HttpRequest::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->client = $this->getMock(Client::class);
        $this->local_machine_helper = $this->getMockBuilder(LocalMachineHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->filesystem = $this->getMockBuilder(Filesystem::class)
            ->disableOriginalConstructor()
            ->getMock();

        $terminusVersion = '1.1.1';
        $phpVersion = '7.0.0';
        $script = 'foo/bar/baz.php';
        $platformScript = str_replace('/', DIRECTORY_SEPARATOR, $script);
        $this->user_agent = "Terminus/$terminusVersion (php_version=$phpVersion&script=$platformScript)";

        $this->config = new TerminusConfig();
        $this->config->set('host', 'example.com');
        $this->config->set('protocol', 'https');
        $this->config->set('port', '443');
        $this->config->set('version', $terminusVersion);
        $this->config->set('script', $script);
        $this->config->set('php_version', $phpVersion);

        $this->container = $this->getMock(Container::class);
        $this->session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMock(LoggerInterface::class);

        $this->request->setContainer($this->container);
        $this->request->setConfig($this->config);
        $this->request->setSession($this->session);
        $this->request->setLogger($this->logger);
    }

    /**
     * Tests a successful download
     */
    public function testDownload()
    {
        $domain = 'pantheon.io';
        $url = "http://$domain/somefile.tar.gz";
        $target = 'some local path';

        $this->container->expects($this->at(0))
            ->method('get')
            ->with($this->equalTo(LocalMachineHelper::class))
            ->willReturn($this->local_machine_helper);
        $this->local_machine_helper->expects($this->once())
            ->method('getFilesystem')
            ->with()
            ->willReturn($this->filesystem);
        $this->filesystem->expects($this->once())
            ->method('exists')
            ->with($target)
            ->willReturn(false);
        $this->container->expects($this->at(1))
            ->method('get')
            ->with(
                $this->equalTo(Client::class),
                $this->equalTo([['base_uri' => $domain, RequestOptions::VERIFY => true,],])
            )
            ->willReturn($this->client);
        $this->client->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo('GET'),
                $this->equalTo($url),
                $this->equalTo(['sink' => $target,])
            );

        $out = $this->request->download($url, $target);
        $this->assertNull($out);
    }

    /**
     * Tests an unsuccessful download because the target file already exists
     */
    public function testDownloadPathExists()
    {
        $domain = 'pantheon.io';
        $url = "http://$domain/somefile.tar.gz";
        $target = 'some local path';

        $this->container->expects($this->once())
            ->method('get')
            ->with($this->equalTo(LocalMachineHelper::class))
            ->willReturn($this->local_machine_helper);
        $this->local_machine_helper->expects($this->once())
            ->method('getFilesystem')
            ->with()
            ->willReturn($this->filesystem);
        $this->filesystem->expects($this->once())
            ->method('exists')
            ->with($target)
            ->willReturn(true);
        $this->client->expects($this->never())
            ->method('request');

        $this->setExpectedException(TerminusException::class, "Target file $target already exists.");

        $out = $this->request->download($url, $target);
        $this->assertNull($out);
    }

    public function testRequest()
    {
        $this->session->method('get')->with('session')->willReturn(false);

        $client_options = ['base_uri' => 'https://example.com:443', RequestOptions::VERIFY => true];

        $method = 'GET';
        $uri = 'https://example.com:443/api/foo/bar';
        $headers = [
            'foo' => 'bar',
            'Content-type' => 'application/json',
            'User-Agent' => $this->user_agent,
        ];
        $body = '';
        $request_options = [$method, $uri, $headers, $body];
        $actual = $this->makeRequest($client_options, $request_options, 'foo/bar', ['headers' => ['foo' => 'bar']]);
        $expected = [
            'data' => (object)['abc' => '123'],
            'headers' => ['Content-type' => 'application/json'],
            'status_code' => 200,
        ];
        $this->assertEquals($expected, $actual);
    }

    public function testRequestAuth()
    {
        $this->session->method('get')->with('session')->willReturn('abc123');

        $client_options = ['base_uri' => 'https://example.com:443', RequestOptions::VERIFY => true];
        $method = 'GET';
        $uri = 'https://example.com:443/api/foo/bar';
        $headers = [
            'Content-type' => 'application/json',
            'User-Agent' => $this->user_agent,
            'Authorization' => 'Bearer abc123'
        ];
        $body = '';
        $request_options = [$method, $uri, $headers, $body];
        $this->makeRequest($client_options, $request_options, 'foo/bar');
    }

    public function testRequestFullPath()
    {
        $this->config->set('verify_host_cert', false);
        $this->session->method('get')->with('session')->willReturn('abc123');

        $client_options = ['base_uri' => 'https://example.com:443', RequestOptions::VERIFY => false];

        $method = 'GET';
        $uri = 'http://foo.bar/a/b/c';
        $headers = [
            'Content-type' => 'application/json',
            'User-Agent' => $this->user_agent,
        ];
        $body = '';
        $request_options = [$method, $uri, $headers, $body];
        $this->makeRequest($client_options, $request_options, 'http://foo.bar/a/b/c');
    }

    public function testRequestWithQuery()
    {
        $this->session->method('get')->with('session')->willReturn(false);

        $client_options = ['base_uri' => 'https://example.com:443', RequestOptions::VERIFY => true];

        $method = 'GET';
        $uri = 'https://example.com:443/api/foo/bar?foo=bar';
        $headers = [
          'Content-type' => 'application/json',
          'User-Agent' => $this->user_agent,
        ];
        $body = '';
        $request_options = [$method, $uri, $headers, $body];
        $actual = $this->makeRequest($client_options, $request_options, 'foo/bar', ['query' => ['foo' => 'bar']]);
        $expected = [
          'data' => (object)['abc' => '123'],
          'headers' => ['Content-type' => 'application/json'],
          'status_code' => 200,
        ];
        $this->assertEquals($expected, $actual);
    }


    public function testRequestNoVerify()
    {
        $this->config->set('verify_host_cert', false);
        $this->session->method('get')->with('session')->willReturn(false);

        $client_options = ['base_uri' => 'https://example.com:443', RequestOptions::VERIFY => false];

        $method = 'GET';
        $uri = 'https://example.com:443/api/foo/bar';
        $headers = [
            'Content-type' => 'application/json',
            'User-Agent' => $this->user_agent,
        ];
        $body = '';
        $request_options = [$method, $uri, $headers, $body];
        $this->makeRequest($client_options, $request_options, 'foo/bar');
    }

    /**
     * Test Request::pagedRequest() when the second query's data comes back empty
     */
    public function testPagedRequestWhenSecondQueryEmpty()
    {
        $this->session->method('get')->with('session')->willReturn(false);

        $client_options = ['base_uri' => 'https://example.com:443', RequestOptions::VERIFY => true];

        $method = 'GET';
        $uri = 'https://example.com:443/api/foo/bar';
        $headers = [
            'Content-type' => 'application/json',
            'User-Agent' => $this->user_agent,
        ];
        $body = '';
        $request_options = [$method, $uri, $headers, $body];

        $prefix = 'abc_';
        $expected_options = $expected_options_2 = $request_options;
        $limit = Request::PAGED_REQUEST_ENTRY_LIMIT;
        $expected_options[1] .= "?limit=$limit";
        $expected_options_2[1] .= "?limit=$limit&start=$prefix" . ($limit - 1);
        $expected_objects = [];
        for ($i = 0; $i < $limit; $i++) {
            $id = $prefix . $i;
            $expected_objects[$id] = (object)compact('id');
        }

        $this->container->expects($this->at(0))
            ->method('get')
            ->with(Client::class, [$client_options,])
            ->willReturn($this->client);
        $this->container->expects($this->at(1))
            ->method('get')
            ->with(HttpRequest::class, $expected_options)
            ->willReturn($this->http_request);
        $this->container->expects($this->at(2))
            ->method('get')
            ->with(Client::class, [$client_options,])
            ->willReturn($this->client);
        $this->container->expects($this->at(3))
            ->method('get')
            ->with(HttpRequest::class, $expected_options_2)
            ->willReturn($this->http_request);

        $message = $this->getMock(Response::class);
        $body = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->getMock();

        $body->expects($this->at(0))
            ->method('getContents')
            ->willReturn(json_encode($expected_objects));
        $body->expects($this->at(1))
            ->method('getContents')
            ->willReturn(json_encode([]));
        $message->expects($this->exactly(2))
            ->method('getBody')
            ->willReturn($body);
        $message->expects($this->exactly(2))
            ->method('getHeaders')
            ->willReturn(['Content-type' => 'application/json',]);
        $message->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $this->client->expects($this->exactly(2))
            ->method('send')
            ->with($this->http_request)
            ->willReturn($message);

        $actual = $this->request->pagedRequest($uri, $request_options);
        $this->assertEquals(['data' => $expected_objects,], $actual);
    }

    /**
     * Test Request::pagedRequest() when the second query's data comes back with fewer than the limit of results
     */
    public function testPagedRequestWhenSecondQueryNotFull()
    {
        $this->session->method('get')->with('session')->willReturn(false);

        $client_options = ['base_uri' => 'https://example.com:443', RequestOptions::VERIFY => true];

        $method = 'GET';
        $uri = 'https://example.com:443/api/foo/bar';
        $headers = [
            'Content-type' => 'application/json',
            'User-Agent' => $this->user_agent,
        ];
        $body = '';
        $request_options = [$method, $uri, $headers, $body];

        $prefix = 'abc_';
        $expected_options = $expected_options_2 = $request_options;
        $limit = Request::PAGED_REQUEST_ENTRY_LIMIT;
        $expected_options[1] .= "?limit=$limit";
        $expected_options_2[1] .= "?limit=$limit&start=$prefix" . ($limit - 1);
        $expected_objects = [];
        for ($i = 0; $i < $limit; $i++) {
            $id = $prefix . $i;
            $expected_objects[$id] = (object)compact('id');
        }
        $expected_objects_2 = [];
        for ($i = $limit; $i < $limit + floor($limit / 2); $i++) {
            $id = $prefix . $i;
            $expected_objects_2[$id] = (object)compact('id');
        }

        $this->container->expects($this->at(0))
            ->method('get')
            ->with(Client::class, [$client_options,])
            ->willReturn($this->client);
        $this->container->expects($this->at(1))
            ->method('get')
            ->with(HttpRequest::class, $expected_options)
            ->willReturn($this->http_request);
        $this->container->expects($this->at(2))
            ->method('get')
            ->with(Client::class, [$client_options,])
            ->willReturn($this->client);
        $this->container->expects($this->at(3))
            ->method('get')
            ->with(HttpRequest::class, $expected_options_2)
            ->willReturn($this->http_request);

        $message = $this->getMock(Response::class);
        $body = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->getMock();

        $body->expects($this->at(0))
            ->method('getContents')
            ->willReturn(json_encode($expected_objects));
        $body->expects($this->at(1))
            ->method('getContents')
            ->willReturn(json_encode($expected_objects_2));
        $message->expects($this->exactly(2))
            ->method('getBody')
            ->willReturn($body);
        $message->expects($this->exactly(2))
            ->method('getHeaders')
            ->willReturn(['Content-type' => 'application/json',]);
        $message->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $this->client->expects($this->exactly(2))
            ->method('send')
            ->with($this->http_request)
            ->willReturn($message);

        $actual = $this->request->pagedRequest($uri, $request_options);
        $this->assertEquals(['data' => $expected_objects + $expected_objects_2,], $actual);
    }

    /**
     * Test Request::pagedRequest() when the second query's data comes back with the same ending obj as the first
     */
    public function testPagedRequestWhenSecondQueryRepeats()
    {
        $this->session->method('get')->with('session')->willReturn(false);

        $client_options = ['base_uri' => 'https://example.com:443', RequestOptions::VERIFY => true];

        $method = 'GET';
        $uri = 'https://example.com:443/api/foo/bar';
        $headers = [
            'Content-type' => 'application/json',
            'User-Agent' => $this->user_agent,
        ];
        $body = '';
        $request_options = [$method, $uri, $headers, $body];

        $prefix = 'abc_';
        $expected_options = $expected_options_2 = $request_options;
        $limit = Request::PAGED_REQUEST_ENTRY_LIMIT;
        $expected_options[1] .= "?limit=$limit";
        $expected_options_2[1] .= "?limit=$limit&start=$prefix" . ($limit - 1);
        $expected_objects = [];
        for ($i = 0; $i < $limit; $i++) {
            $id = $prefix . $i;
            $expected_objects[$id] = (object)compact('id');
        }

        $this->container->expects($this->at(0))
            ->method('get')
            ->with(Client::class, [$client_options,])
            ->willReturn($this->client);
        $this->container->expects($this->at(1))
            ->method('get')
            ->with(HttpRequest::class, $expected_options)
            ->willReturn($this->http_request);
        $this->container->expects($this->at(2))
            ->method('get')
            ->with(Client::class, [$client_options,])
            ->willReturn($this->client);
        $this->container->expects($this->at(3))
            ->method('get')
            ->with(HttpRequest::class, $expected_options_2)
            ->willReturn($this->http_request);

        $message = $this->getMock(Response::class);
        $body = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->getMock();

        $body->expects($this->at(0))
            ->method('getContents')
            ->willReturn(json_encode($expected_objects));
        $body->expects($this->at(1))
            ->method('getContents')
            ->willReturn(json_encode($expected_objects));
        $message->expects($this->exactly(2))
            ->method('getBody')
            ->willReturn($body);
        $message->expects($this->exactly(2))
            ->method('getHeaders')
            ->willReturn(['Content-type' => 'application/json',]);
        $message->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $this->client->expects($this->exactly(2))
            ->method('send')
            ->with($this->http_request)
            ->willReturn($message);

        $actual = $this->request->pagedRequest($uri, $request_options);
        $this->assertEquals(['data' => $expected_objects,], $actual);
    }

    private function makeRequest($client_options, $request_options, $url, $options = [])
    {
        $this->container->expects($this->at(0))
            ->method('get')
            ->with(Client::class, [$client_options])
            ->willReturn($this->client);
        $this->container->expects($this->at(1))
            ->method('get')
            ->with(HttpRequest::class, $request_options)
            ->willReturn($this->http_request);

        $message = $this->getMock(Response::class);
        $body = $this->getMockBuilder(Stream::class)
            ->disableOriginalConstructor()
            ->getMock();
        $body->method('getContents')->willReturn(json_encode(['abc' => '123']));
        $message->expects($this->once())
            ->method('getBody')
            ->willReturn($body);
        $message->expects($this->once())
            ->method('getHeaders')
            ->willReturn(['Content-type' => 'application/json']);
        $message->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $this->client->expects($this->once())
            ->method('send')
            ->with($this->http_request)
            ->willReturn($message);

        return $this->request->request($url, $options);
    }
}
