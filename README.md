# Installation
Just put `mlp` folder into your `modules/custom` directory and enable it 
by using terminal command `drush en mlp`.

# Usage
* Mortgage Loan Payments calculator is available as block 
(can be found between all the other Drupal blocks) and as page (`/mortgage-loan-payments`).
* All calculations are logged into custom created `mlp` logger channel and can be found here 
`/admin/reports/dblog`.
* This module provides possibility to get loan summary using web service by providing
required parameters. To make it work you need to set custom permission 
`Access GET on Loan summary resource resource` to specific user role and make `GET` request 
`/api/loan_summary?_format=json`.
Example: `/api/loan_summary?optional_extra_payments=100&number_of_payments_per_year=12&loan_period_in_years=30&annual_interest_rate=20&loan_amount=65000&start_date_of_loan=22/01/2018&_format=json`

# Dependencies
Module has these dependencies:
* rest

# Uninstall
If you want to uninstall this module you can do it by using terminal command `drush pm-uninstall mlp`.