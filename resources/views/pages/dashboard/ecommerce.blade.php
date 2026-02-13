@extends('layouts.app')
<script>
    window.dashboardData = @json($dashboardData);
</script>
@section('content')
  <div class="grid grid-cols-12 gap-4 md:gap-6">
    <div class="col-span-12 space-y-6 xl:col-span-7">
      <x-ecommerce.ecommerce-metrics :dashboardData="$dashboardData" />
      <x-ecommerce.monthly-sale />
    </div>
    <div class="col-span-12 xl:col-span-5">
        <x-ecommerce.monthly-target :occupancyData="$dashboardData['occupancyData']" />
    </div>

    <div class="col-span-12">
      <x-ecommerce.statistics-chart :dashboardData="$dashboardData" />
    </div>

    <div class="col-span-12 xl:col-span-5">
      <x-ecommerce.customer-demographic :financialData="$dashboardData['financialData']" />
    </div>

    <div class="col-span-12 xl:col-span-7">
      <x-ecommerce.recent-orders :orders="$dashboardData['recentOrders']" />
    </div>
  </div>
@endsection
