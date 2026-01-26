<?php

namespace Misakstvanu\LaravelFortifyPasskeys\Tests\Unit;

use Misakstvanu\LaravelFortifyPasskeys\Services\PasskeyService;
use Misakstvanu\LaravelFortifyPasskeys\Tests\TestCase;
use Symfony\Component\Serializer\SerializerInterface;

class PasskeyServiceTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_the_serializer()
    {
        $service = new PasskeyService();

        $reflection = new \ReflectionMethod($service, 'getSerializer');
        $reflection->setAccessible(true);

        $serializer = $reflection->invoke($service);

        $this->assertInstanceOf(SerializerInterface::class, $serializer);
    }

    /** @test */
    public function it_can_generate_registration_options()
    {
        $service = new PasskeyService();
        $request = \Illuminate\Http\Request::create('/options', 'POST', [
            'email' => 'test@example.com'
        ]);
        $request->setLaravelSession(app('session.store'));

        $options = $service->generateOptions($request, null, true);

        $this->assertArrayHasKey('rp', $options);
        $this->assertArrayHasKey('user', $options);
        $this->assertArrayHasKey('challenge', $options);
        $this->assertArrayHasKey('pubKeyCredParams', $options);
        $this->assertEquals('test@example.com', $options['user']['name']);
    }

    /** @test */
    public function it_can_generate_login_options()
    {
        $service = new PasskeyService();
        $request = \Illuminate\Http\Request::create('/options', 'POST');
        $request->setLaravelSession(app('session.store'));

        $options = $service->generateOptions($request, null, false);

        $this->assertArrayHasKey('challenge', $options);
        $this->assertArrayNotHasKey('user', $options);
    }

    /** @test */
    public function it_throws_exception_if_session_expired_on_verify()
    {
        $service = new PasskeyService();
        $request = \Illuminate\Http\Request::create('/verify', 'POST', [], [], [], [], json_encode(['id' => 'abc', 'rawId' => 'abc', 'type' => 'public-key', 'response' => []]));
        $request->setLaravelSession(app('session.store'));

        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $psrHttpFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $serverRequest = $psrHttpFactory->createRequest($request);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Session expired');

        $service->verify($request, $serverRequest);
    }
}
