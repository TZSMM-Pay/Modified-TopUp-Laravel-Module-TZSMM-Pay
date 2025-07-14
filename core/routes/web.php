  Route::match(['get', 'post'], '/deposit/{trx}/{gateway}', [PaymentController::class, 'depositIpn'])->name('deposit.ipn')->withoutMiddleware('auth');
    Route::match(['get', 'post'], '/order/{trx}/{gateway}', [PaymentController::class, 'orderIpn'])->name('order.ipn')->withoutMiddleware('auth');
