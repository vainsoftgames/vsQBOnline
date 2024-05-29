# vsQBOnline
This PHP wrapper allows you to interact with QuickBooks Online (QBO) using OAuth2 for authentication. It provides various functions to manage tokens, employees, vendors, and categories, and to create expenses and bills for vendors.

This isn't a complete QBO Wrapper, more proof of concept. As I use it, I'll add more functions/features.

# Init
vsQBOnline requires Quickbooks SDK to run, you can git it from: https://github.com/intuit/QuickBooks-V3-PHP-SDK

# Setup
```php
require('path/to/vsQBOnline.php');

$qb = new vsQBOnline();
```


# Details
```
$fields = STRING | Array of strings contained in response payload
$date = INT16 UnixTimestamp
```


# Employee Functions
## Get Employees
```php
$employees = $qb->getEmployees();
```

## Get Employee by ID
```php
$employee = $qb->getEmployee($id);
```

# Vendor Functions
## Get Vendors
```php
$vendors = $qb->getVendors($fields);
```

## Add Expense to Vendor
```php
$lines = [
  $qb->createLineItem($amount, $accountID, $desc);
  // Add more lines as needed
];
$result = $qb->addExpenseToVendor($accountID, $methodID, $vendorID, $date, $lines, $paymentType, $tags, $note);
```

## Add Bill to Vendor (WIP)
```php
$lines = [
  $qb->createLineItem($amount, $accountID, $desc);
  /// Add more lines as needed
];

$result = $qb->addBillToVendor($vendorID, $date, $lines, $paymentType, $note);
```


# Category Functions
## Get Categories
```php
$categories = $qb->getCategories($fields);
```
