<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: ydb_import_v1.proto

namespace GPBMetadata;

class YdbImportV1
{
    public static $is_initialized = false;

    public static function initOnce() {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();

        if (static::$is_initialized == true) {
          return;
        }
        \GPBMetadata\Protos\YdbImport::initOnce();
        $pool->internalAddGeneratedFile(
            '
�
ydb_import_v1.proto

ImportFromS3.Ydb.Import.ImportFromS3Request .Ydb.Import.ImportFromS3ResponseK

ImportData.Ydb.Import.ImportDataRequest.Ydb.Import.ImportDataResponseBL
tech.ydb.import_.v1Z5github.com/ydb-platform/ydb-go-genproto/Ydb_Import_V1bproto3'
        , true);

        static::$is_initialized = true;
    }
}
