# 1.8.1

* fixed bug, when function Retry::backoffType always return SlowBackoff

# 1.8.0

* update destructor in MemorySessionPool
* fixed exception on re-create server nodes
* fixed key name in createTable function
* added simple std looger

## 1.7.0

* added environment credentials

## 1.6.0

* added retry function
* fixed result with empty list
* added optional type in prepare statment

## 1.5.6

* added support of php 7.2

## 1.5.5

* fixed iam auth for PHP < 8.0
* added examples
* updated saveToken function in Iam.php

## 1.5.4

* fixed jwt authentication

## 1.5.3

* removed query id in prepare statement

## 1.5.2

* fixed refresh token when it expired
* fixed retry at BAD_SESSION
* added credentials authentication
* added CI test

## 1.5.1

* added access token authentication

## 1.5.0 (2023-02-22)

### Features

- make protobuf
- updated protos
- JWT replacement


## 1.4.5 (2023-02-15)

### Bugs

- improved issue tree


## 1.4.4 (2023-02-15)

### Bugs

- uint64 type casting


## 1.4.3 (2023-02-15)

### Features

- anonymous authentication method
- insecure grpc connection


## 1.4.2 (2023-01-13)

### Bugs

- fixed lcobucci/jwt to 4.1.5 version


## 1.4.1 (2022-11-08)

### Features

- updated namespace


## 1.4.0 (2022-05-16)

### Features

- updated namespace


## 1.3.1 (2022-02-25)

### Features

- improved display of issue messages


## 1.3.0 (2022-02-25)

### Features

- introduced the query builder to customize the query settings


## 1.2.0 (2022-02-09)

### Features

- added PHP 8 support
- updated lcobucci/jwt to 4 version


## 1.1.1 (2021-11-01)

### Features

- implemented readTable option: key_range

### Bugs

- YdbType conversion fix for integers


## 1.1.0 (2021-10-26)

### Features

- implemented auth with metadata url

### Bugs

- implemented converting int8 & int16 to typed value


## 1.0.14 (2021-10-22)

### Features

- readTable options: limit, ordered

### Bugs

- createTable composite PK annotation
