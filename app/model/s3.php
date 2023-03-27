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
    private string $bucket;

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
            $extension = explode('.', $fromKey);
            $extension = end($extension);
            return $this->client->copy(
                $fromBucket ?? $this->bucket,
                $fromKey,
                $destBucket ?? $this->bucket,
                $destKey ?? bin2hex($this->getRandomKey($extension, $destBucket ?? $this->bucket)) . '.' . $extension,
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

    public function deleteObjects(array $objects, string $bucket = null)
    {
        return $this->client->deleteObjects([
            'Bucket' => $bucket ?? $this->bucket,
            // 'Key' => $file_name,
            'Delete' => [
                'Objects' => $objects,
            ],
        ]);
    }

    public function emptyBucket(string $bucket = null)
    {
        try {
            $objects = [];
            foreach ($this->listObjects()['Contents'] as $content) {
                $objects[] = [
                    'Key' => $content['Key'],
                ];
            }
            $this->deleteObjects($objects);
            $check = $this->listObjects();
            if (count($check) <= 0) {
                throw new Exception("Bucket wasn't empty.");
            }
            echo 'Deleted all objects and folders from ' . $bucket ?? $this->bucket . PHP_EOL;
        } catch (Exception $exception) {
            echo 'Failed to empty bucket ' . $bucket ?? $this->bucket . ' with error: ' . $exception->getMessage();
            exit("Please fix error with object deletion before continuing.");
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

    public function getEtag(string $key, string $bucket = null)
    {
        try {
            return str_replace('"', '', $this->client->headObject([
                'Bucket' => $bucket ?? $this->bucket,
                'Key'    => $key,
            ])['ETag']);
        } catch (S3Exception $e) {
            print($e->getMessage() . PHP_EOL);
        }
    }

    public function getRandomKey(string $ext = null, string $bucket = null)
    {
        $newKey = random_bytes(8);
        // $newKey = bin2hex(random_bytes(24));
        while ($this->objectExist(empty($ext) ? bin2hex($newKey) : bin2hex($newKey) . '.' . $ext, $bucket ?? $this->bucket))
            $newKey = random_bytes(8);
        // $newKey = bin2hex(random_bytes(24));
        return $newKey;
    }

    public function listBuckets()
    {
        return $this->client->listBuckets();
    }

    public function listObjects(string $bucket = null)
    {
        try {
            return $this->client->listObjects([
                'Bucket' => $bucket ?? $this->bucket,
            ]);
        } catch (AwsException $e) {
            print($e);
        }
    }

    public function move(string $fromKey, string $destKey = null, string $fromBucket = null, string $destBucket = null)
    {
        try {
            $extension = explode('.', $fromKey);
            $extension = end($extension);

            if (!empty($destKey)) {
                $binKey = explode('.', $destKey);
                $binKey = hex2bin($binKey[0]);
            } else $binKey = $this->getRandomKey($extension, $destBucket ?? $this->bucket);

            $newKey = $destKey ?? bin2hex($binKey) . '.' . $extension;
            $this->client->copy(
                $fromBucket ?? $this->bucket,
                $fromKey,
                $destBucket ?? $this->bucket,
                $newKey
            );
            $this->deleteObject($fromKey);
            return ['binKey' => $binKey, 'key' => $newKey, 'ext' => $extension];
        } catch (AwsException $e) {
            return print($e->getMessage() . PHP_EOL);
        }
    }

    public function objectExist(string $key, string $bucket = null, bool $includeDeleteMarkers = false)
    {
        try {
            return $this->client->doesObjectExistV2($bucket ?? $this->bucket, $key);
        } catch (S3Exception $e) {
            return print($e->getMessage() . PHP_EOL);
        }
    }

    public function presignedPostForm()
    {
    }

    public function presignedUriGet(string $key, ?string $bucket = null, string $expiration = '+5 minutes')
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
    public function presignedUriPut(array $options)
    {
        try {
            $key = $options['key'] ?? bin2hex($this->getRandomKey($options['ext'], $options['bucket'] ?? $this->bucket)) . '.' . $options['ext'];
            $cmd = $this->client->getCommand('PutObject', [
                'Bucket' => $options['bucket'] ?? $this->bucket,
                'Key' => $key,
                'ContentType' => $options['type'] ?? 'image/jpeg',
            ]);
            $request = $this->client->createPresignedRequest($cmd, $options['expiration'] ?? '+5 minutes')->withMethod('PUT');
            return ['bucket' => $options['bucket'] ?? $this->bucket, 'key' => $key, 'uri' => $request->getUri()];
        } catch (S3Exception $e) {
            print($e->getMessage() . PHP_EOL);
        }
    }

    public function put(array $options)
    {
        try {
            return $this->client->putObject([
                'Bucket' => $options['bucket'] ?? $this->bucket,
                'Body' => $options['body'] ?? null,
                'Key' => $options['key'] ?? (bin2hex($this->getRandomKey($options['extension'], $options['bucket'] ?? $this->bucket)) . '.' . $options['extension']),
                'SourceFile' => $options['path'] ?? null,
                // 'ContentLength' => $options['length'] ?? null,
            ]);
        } catch (S3Exception $e) {
            print($e->getMessage() . PHP_EOL);
        }
    }

    // public function putFromUrl(array $options)
    // {
    //     try {
    //         $file = file_get_contents($options['body']);

    //         return $this->client->putObject([
    //             'Bucket' => $options['bucket'] ?? $this->bucket,
    //             'Body' => $file,
    //             'Key' => $options['key'] ?? $this->getRandomKey($options['bucket'] ?? $this->bucket),
    //             // 'SourceFile' => $options['path'] ?? null,
    //             // 'ContentLength' => $content_length,
    //         ]);
    //     } catch (S3Exception $e) {
    //         print($e->getMessage() . PHP_EOL);
    //     }
    // }
}
