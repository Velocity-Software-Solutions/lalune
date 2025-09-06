@extends('layouts.app')

@section('content')
<section class="max-w-5xl mx-auto px-6 py-12">
  <h1 class="text-4xl font-bold mb-6">Terms &amp; Conditions</h1>

  <p class="text-gray-700 mb-8">
    Welcome to LaLune by NE. By accessing or using our website and purchasing our products, 
    you agree to the following Terms and Conditions. Please read them carefully.
  </p>

  <div class="space-y-8">

    <!-- 1. General -->
    <div>
      <h2 class="text-2xl font-semibold mb-2">1. General</h2>
      <p class="text-gray-700">
        These Terms and Conditions govern your use of our website and services. 
        We reserve the right to update or modify these terms at any time without prior notice.
      </p>
    </div>

    <!-- 2. Products -->
    <div>
      <h2 class="text-2xl font-semibold mb-2">2. Products</h2>
      <p class="text-gray-700">
        All items are described and presented as accurately as possible. 
        Colors, sizes, and materials may vary slightly due to photography and display settings.
      </p>
    </div>

    <!-- 3. Orders & Payments -->
    <div>
      <h2 class="text-2xl font-semibold mb-2">3. Orders &amp; Payments</h2>
      <ul class="list-disc pl-6 text-gray-700 space-y-2">
        <li>All orders are subject to availability.</li>
        <li>We accept major credit cards, debit cards, and other payment methods listed at checkout.</li>
        <li>Prices are shown in CAD (Canadian Dollars) unless otherwise stated.</li>
      </ul>
    </div>

    <!-- 4. Shipping & Delivery -->
    <div>
      <h2 class="text-2xl font-semibold mb-2">4. Shipping &amp; Delivery</h2>
      <p class="text-gray-700">
        Orders are processed and shipped within the timelines provided at checkout. 
        Delivery times may vary depending on destination and courier service. 
        We are not responsible for delays outside of our control.
      </p>
    </div>

    <!-- 5. Returns & Exchanges -->
    <div>
      <h2 class="text-2xl font-semibold mb-2">5. Returns &amp; Exchanges</h2>
      <p class="text-gray-700">
        We accept returns under the following conditions:
      </p>
      <ul class="list-disc pl-6 text-gray-700 space-y-2 mt-2">
        <li>Items must be <b>unused</b> and in original condition with tags attached.</li>
        <li>Returns must be initiated <b>within 7 days</b> for a refund or store credit.</li>
        <li>Returns between <b>8–14 days</b> are eligible for store credit only.</li>
        <li>Final sale items and gift cards are non-returnable.</li>
      </ul>
    </div>

    <!-- 6. Intellectual Property -->
    <div>
      <h2 class="text-2xl font-semibold mb-2">6. Intellectual Property</h2>
      <p class="text-gray-700">
        All content on this website, including images, text, logos, and designs, 
        are the property of LaLune by NE and protected by intellectual property laws. 
        You may not copy, reproduce, or use our content without prior written consent.
      </p>
    </div>

    <!-- 7. Limitation of Liability -->
    <div>
      <h2 class="text-2xl font-semibold mb-2">7. Limitation of Liability</h2>
      <p class="text-gray-700">
        We are not liable for any damages arising from the use or misuse of our products or website. 
        This includes but is not limited to indirect, incidental, or consequential damages.
      </p>
    </div>

    <!-- 8. Governing Law -->
    <div>
      <h2 class="text-2xl font-semibold mb-2">8. Governing Law</h2>
      <p class="text-gray-700">
        These Terms & Conditions are governed by the laws of Ontario, Canada. 
        Any disputes shall be resolved in the applicable courts of Ontario.
      </p>
    </div>

    <!-- 9. Contact Us -->
    <div>
      <h2 class="text-2xl font-semibold mb-2">9. Contact Us</h2>
      <p class="text-gray-700">
        For questions regarding these Terms & Conditions, please contact us at 
        <a href="mailto:support@lalunebyne.com" class="text-black font-medium underline">support@lalunebyne.com</a>.
      </p>
    </div>

  </div>
</section>
@endsection
