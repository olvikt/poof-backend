#!/usr/bin/env bash
set -euo pipefail

php artisan test \
  tests/Feature/Api/OrderStoreTest.php \
  tests/Unit/OrderLifecycleStatusContractTest.php \
  tests/Unit/Support/Address/AddressPrecisionTest.php \
  tests/Unit/Support/Address/AddressCoordinatePolicyTest.php \
  tests/Feature/Admin/AdminProtectedRoutesAuthTest.php \
  tests/Feature/Api/ApiProtectedRoutesAuthTest.php \
  tests/Feature/Auth/RegisterTest.php \
  tests/Feature/Auth/PasswordResetTest.php \
  tests/Feature/Courier/AcceptFlowArchitectureRegressionTest.php \
  tests/Feature/Courier/CourierRuntimeStateSyncTest.php \
  tests/Feature/Courier/AvailableOrdersOnlineSyncTest.php \
  tests/Feature/Courier/CourierOnlineNavigationSyncTest.php \
  tests/Feature/Api/GeocodeControllerTest.php \
  tests/Unit/Address/PrepareAddressSavePayloadTest.php \
  tests/Unit/Address/FilterClientAddressPayloadTest.php \
  tests/Unit/Address/PersistAddressDataTest.php \
  tests/Unit/Address/PersistClientAddressActionTest.php \
  tests/Unit/Address/ResolveAddressFromPointTest.php \
  tests/Unit/Address/ResolveAddressPointFromFieldsTest.php \
  tests/Unit/Orders/LifecycleActionContractsTest.php \
  tests/Unit/Orders/OrderLifecycleTransitionPolicyTest.php \
  tests/Feature/Subscriptions/GenerateSubscriptionExecutionOrdersCommandTest.php \
  tests/Feature/Subscriptions/SubscriptionExecutionIdempotencyMigrationTest.php \
  tests/Feature/Payments/WayForPayReturnFlowTest.php \
  tests/Feature/Courier/OfferDispatcherSubscriptionDiagnosticsTest.php \
  tests/Feature/Admin/AdminApiRouteMiddlewareContractTest.php \
  tests/Feature/Admin/AdminRuntimeDiagnosticsApiTest.php \
  tests/Feature/Payments/ClientPaymentPublicIdSecurityTest.php \
  tests/Feature/Api/PendingConfirmationsTest.php \
  tests/Feature/Api/CourierAvailableOrdersApiTest.php \
  "$@"
