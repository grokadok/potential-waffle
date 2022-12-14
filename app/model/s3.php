<?php

namespace bopdev;

require __DIR__ . '/../../vendor/autoload.php';

use Aws\S3\S3Client as S3;
use Aws\S3\Exception\S3Exception;
use Aws\Exception\AwsException;
use Exception;

class S3Client
{
    private $client;
    private $bucket;

    public function __construct()
    {
        $this->client = new S3([
            'version' => 'latest',
            'region' => 'us-east-2',
            'endpoint' => 'https://' . getenv('CELLAR_ADDON_HOST')
        ]);
        $this->bucket = getenv('CELLAR_ADDON_BUCKET');
    }

    // TODO: add create/(update)/delete bucket functions

    public function bucketExist(string $bucket, bool $accept403 = false)
    {
        try {
            return $this->client->doesBucketExistV2($bucket, $accept403);
        } catch (S3Exception $e) {
            return print($e->getMessage() . PHP_EOL);
        }
    }

    public function bucketIsEmpty()
    {
        // TODO: code bucketIsEmpty method
    }

    public function copy(string $fromKey, string $destKey = null, string $fromBucket = null, string $destBucket = null)
    {
        try {
            return $this->client->copy(
                $fromBucket ?? $this->bucket,
                $fromKey,
                $destBucket ?? $this->bucket,
                $destKey ?? $this->getRandomKey($destBucket ?? $this->bucket)
            );
        } catch (AwsException $e) {
            return print($e->getMessage() . PHP_EOL);
        }
    }

    public function createBucket(?string $name = null)
    {
        try {
            $result = $this->client->createBucket([
                'Bucket' => $name ?? bin2hex(random_bytes(64)),
            ]);
            var_dump($result);
            return [
                'location' => $result['Location'],
                'uri' => $result['@metadata']['effectiveUri'],
            ];
        } catch (AwsException $e) {
            return print($e->getMessage() . PHP_EOL);
        }
    }

    public function deleteBucket($name)
    {
        try {
            // TODO: check if bucket empty, else delete objects in it before bucket deletion.
            return $this->client->deleteBucket([
                'Bucket' => $name,
            ]);
        } catch (AwsException $e) {
            return print($e->getMessage() . PHP_EOL);
        }
    }

    public function deleteObject(string $key, string $bucket = null)
    {
        // TODO: try function deleteObject
        try {
            return $this->client->deleteObject([
                'Bucket' => $bucket ?? $this->bucket,
                'Key' => $key,
            ]);
        } catch (S3Exception $e) {
            print($e->getMessage() . PHP_EOL);
        }
    }

    public function get(string $key, string $bucket = null)
    {
        try {
            return $this->client->getObject([
                'Bucket' => $bucket ?? $this->bucket,
                'Key'    => $key,
            ]);
        } catch (S3Exception $e) {
            print($e->getMessage() . PHP_EOL);
        }
    }

    public function getRandomKey(string $bucket = null)
    {
        $newKey = bin2hex(random_bytes(64));
        while ($this->objectExist($newKey, $bucket ?? $this->bucket))
            $newKey = bin2hex(random_bytes(64));
        return $newKey;
    }

    public function listBuckets()
    {
        return $this->client->listBuckets();
    }

    public function listObjects(string $bucket)
    {
        return $this->client->listObjects([
            'Bucket' => $bucket,
        ]);
    }

    public function objectExist(string $key, string $bucket = null, bool $includeDeleteMarkers = false)
    {
        try {
            return $this->client->doesObjectExistV2($bucket ?? $this->bucket, $key);
        } catch (S3Exception $e) {
            return print($e->getMessage() . PHP_EOL);
        }
    }

    public function presignedUrlGet(string $key, ?string $bucket = null, string $expiration = '+5 minutes')
    {
        try {
            $cmd = $this->client->getCommand('GetObject', [
                'Bucket' => $bucket ?? $this->bucket,
                'Key' => $key,
            ]);
            $request = $this->client->createPresignedRequest($cmd, $expiration);
            return (string)$request->getUri();
        } catch (S3Exception $e) {
            print($e->getMessage() . PHP_EOL);
        }
    }

    /**
     * Returns a presigned url to put an object of given type into bucket at given key.
     */
    public function presignedUrlPut(string $type = 'image/jpeg', string $key = null, string $bucket = null, string $expiration = '+5 minutes')
    {
        try {
            $cmd = $this->client->getCommand('PutObject', [
                'Bucket' => $bucket ?? $this->bucket,
                'Key' => $key ?? $this->getRandomKey($bucket ?? $this->bucket),
                'ContentType' => $type,
            ]);
            $request = $this->client->createPresignedRequest($cmd, $expiration)->withMethod('PUT');
            return (string)$request->getUri();
        } catch (S3Exception $e) {
            print($e->getMessage() . PHP_EOL);
        }
    }

    public function presignedPostForm()
    {
    }

    public function put(string $path, string $key = null, string $bucket = null)
    {
        try {
            return $this->client->putObject([
                'Bucket' => $bucket ?? $this->bucket,
                'Key' => $key ?? $this->getRandomKey($bucket ?? $this->bucket),
                'SourceFile' => $path,
            ]);
        } catch (S3Exception $e) {
            print($e->getMessage() . PHP_EOL);
        }
    }
}
