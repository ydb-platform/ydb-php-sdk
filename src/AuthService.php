<?php
namespace YdbPlatform\Ydb;
use Ydb\Auth\V1\AuthServiceClient as ServiceClient;
class AuthService{

    use Traits\RequestTrait;
    use Traits\ParseResultTrait;
    use Traits\LoggerTrait;

    /**
     * @var ServiceClient
     */
    protected $client;
    /**
     * @var
     */
    protected $logger;
    /**
     * @var array|mixed
     */
    protected $meta;
    /**
     * @var Iam
     */
    protected $credentials;

    public function __construct(Ydb $ydb, $logger)
    {
        $this->ydb = $ydb;
        $this->logger = $logger;
        $this->client = new ServiceClient($ydb->endpoint(), [
            'credentials' => $ydb->iam()->getCredentials(),
        ]);
        $this->credentials = $ydb->iam();
        $this->meta = [
            'x-ydb-database' => [$ydb->database()],
            'x-ydb-sdk-build-info' => ['ydb-php-sdk/' . Ydb::VERSION],
        ];;
    }

    public function getToken(string $user, string $password){
        $data = [];
        $data["user"] = $user;
        $data["password"] = $password;
        $data["skip_get_token"] = true;
        return $this->doRequest('Auth', 'Login', $data)->getToken();
    }
}
