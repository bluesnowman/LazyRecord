<?php
namespace tests;

class AddressCollectionBase  extends \LazyRecord\BaseCollection {
const schema_proxy_class = '\\tests\\AddressSchemaProxy';
const model_class = '\\tests\\Address';
const table = 'addresses';

}
