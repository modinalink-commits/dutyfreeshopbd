<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('posts')) Schema::create('posts', function (Blueprint $t) {
      $t->id(); $t->string('title'); $t->string('slug')->unique(); $t->string('featured_image')->nullable(); $t->text('excerpt')->nullable(); $t->longText('body')->nullable();
      $t->string('meta_title')->nullable(); $t->text('meta_description')->nullable(); $t->boolean('active')->default(true); $t->timestamp('published_at')->nullable(); $t->timestamps();
    });
    $now = now();
    $pages = [
      'about-us' => ['About Us', 'About Us – DutyFree Shop BD | Trusted Online Store', 'Original products, free delivery and cash on delivery across Bangladesh. Learn about DutyFree Shop BD.', <<<'H'
<p><b>Welcome to DutyFree Shop BD – Your Trusted Online Shopping Destination!</b></p>
<p>We are committed to bringing you 100% authentic products from around the globe — delivered right to your doorstep in Bangladesh. We believe in transparent, hassle-free shopping with no advance payment required — you pay Cash on Delivery.</p>
<h2>Why Choose Us?</h2>
<h3>Original Products</h3><p>We partner with trusted suppliers and brands to ensure every product is genuine and high-quality.</p>
<h3>Free Delivery</h3><p>Enjoy FREE delivery across Bangladesh — no hidden charges, ever.</p>
<h3>Fast &amp; Reliable Service</h3><p>Most orders are delivered within 24 to 72 hours, ensuring speed and satisfaction.</p>
<h3>Customer-Centric Support</h3><p>Our support team is available from [[hours]] to assist you with any questions.</p>
<h3>Safe Shopping</h3><p>We never ask for advance payment — your trust and safety are our top priorities.</p>
<h2>What We Offer</h2>
<p>From lifestyle essentials and electronics to fashion and beauty, we curate a wide range of products to suit your everyday needs. Whether you're a regular shopper or new to online shopping, we make the process smooth, safe, and satisfying.</p>
H],
      'delivery-policy' => ['Delivery Policy', 'Delivery Policy – DutyFree Shop BD | Fast & Reliable Delivery', 'Delivery timeline, charges and order process at DutyFree Shop BD.', <<<'H'
<p>We are committed to delivering your products quickly, safely, and without any hidden costs.</p>
<h2>Delivery Timeline</h2>
<ul><li>Dhaka City: Within 24–48 hours</li><li>Outside Dhaka: Within 48–72 hours</li></ul>
<p>Delivery time may vary slightly due to product type, availability, or unavoidable circumstances (e.g., weather, traffic, etc.).</p>
<h2>Delivery Charges</h2>
<ul><li>FREE Delivery across Bangladesh on selected orders – no hidden charges.</li><li>No advance payment required – we only offer Cash on Delivery (COD).</li></ul>
<h2>Order Process</h2>
<ol><li>Place your order through our website: [[site]]</li><li>You'll receive a confirmation call or SMS.</li><li>Our delivery partner will contact you before delivery.</li></ol>
<h2>Important Notes</h2>
<ul><li>Please provide accurate delivery details (name, phone number, address) to avoid delays.</li><li>If you miss the delivery call, our team will attempt to contact you again before cancellation.</li><li>We do not deliver to P.O. boxes or outside of Bangladesh.</li></ul>
<p>For any delivery-related queries, feel free to contact us:<br>Phone: [[call]]<br>Website: [[site]]</p>
H],
      'privacy-policy' => ['Privacy Policy', 'Privacy Policy – DutyFree Shop BD | Your Data is Safe With Us', 'How DutyFree Shop BD collects, uses and protects your personal information.', <<<'H'
<p>Your privacy and trust are very important to us. This Privacy Policy explains how we collect, use, and protect your personal information when you use our website or services.</p>
<h2>Information We Collect</h2>
<p>When you place an order or contact us, we may collect:</p>
<ul><li>Your name, phone number, and address</li><li>Email (optional)</li><li>Product preferences and order history</li><li>Device and browser information (for analytics)</li></ul>
<h2>How We Use Your Information</h2>
<ul><li>Process and deliver your orders</li><li>Contact you about your order or promotions</li><li>Improve our website and customer service</li><li>Ensure fraud prevention and secure transactions</li></ul>
<h2>We Do Not</h2>
<ul><li>Sell or rent your personal data to any third parties</li><li>Store your financial or payment details (as we only use Cash on Delivery)</li><li>Send spam or irrelevant promotions</li></ul>
<h2>Data Protection</h2>
<p>We use secure systems to keep your data safe. Only authorized team members have access to your information for order processing and customer support purposes.</p>
<h2>Third-Party Links</h2>
<p>Our website may contain links to third-party websites. We are not responsible for their content or privacy practices.</p>
<h2>Your Consent</h2>
<p>By using our website and placing orders, you agree to our Privacy Policy.</p>
<p>For any questions or concerns about your data, please contact us:<br>Phone: [[call]]<br>Website: [[site]]</p>
H],
      'return-policy' => ['Return Policy', 'Return Policy – DutyFree Shop BD | Hassle-Free Returns', 'Return eligibility, how to request a return, replacement and refund at DutyFree Shop BD.', <<<'H'
<p>Customer satisfaction is our top priority. While we ensure the highest quality of products, we understand that issues may occasionally arise. That's why we've made our Return Policy simple and fair.</p>
<h2>Return Eligibility</h2>
<p>You may return a product if:</p>
<ul><li>You received a damaged, defective, or wrong item.</li><li>The product is significantly different from what was described.</li><li>You notify us within 24 hours of receiving the product.</li></ul>
<h2>Non-Returnable Items</h2>
<ul><li>Used or damaged items caused by customer mishandling</li><li>Items without original packaging or tags</li><li>Change of mind after delivery</li><li>Personal care, hygiene, or perishable items</li></ul>
<h2>How to Request a Return</h2>
<ol><li>Contact our support team via WhatsApp or phone within 24 hours.</li><li>Share your order number and clear photos or videos of the issue.</li><li>Our team will verify and arrange a pickup or replacement if eligible.</li></ol>
<h2>Replacement or Refund</h2>
<ul><li>If eligible, we will arrange a replacement at no extra cost.</li><li>If a replacement is not available, we'll process a full refund via your preferred method.</li></ul>
<p>Note: We do not accept advance payments. All purchases are delivered Cash on Delivery (COD) to ensure your safety and confidence.</p>
<p>For any return issues, please reach out to our support team:<br>Phone: [[call]]<br>WhatsApp: [[whatsapp]]<br>Website: [[site]]</p>
H],
    ];
    foreach ($pages as $slug => [$title, $mt, $md, $body]) {
      $ex = DB::table('pages')->where('slug', $slug)->first();
      if (!$ex) DB::table('pages')->insert(['slug'=>$slug,'title'=>$title,'body'=>$body,'meta_title'=>$mt,'meta_description'=>$md,'active'=>true,'created_at'=>$now,'updated_at'=>$now]);
      elseif (trim((string)$ex->body) === '') DB::table('pages')->where('id', $ex->id)->update(['body'=>$body,'meta_title'=>$ex->meta_title ?: $mt,'meta_description'=>$ex->meta_description ?: $md,'updated_at'=>$now]);
    }
    // the old "blog" static page is replaced by the real blog
    DB::table('pages')->where('slug', 'blog')->update(['active'=>false]);
    if (!DB::table('posts')->where('slug', 'dutyfreebd-premium-imported-products')->exists()) DB::table('posts')->insert([
      'title'=>"DutyFree Shop BD – Bangladesh's Trusted Online Duty Free Shop for Premium Imported Products",
      'slug'=>'dutyfreebd-premium-imported-products', 'featured_image'=>null,
      'excerpt'=>'বাংলাদেশে premium imported products ও international cigarette brand কোথায় পাবেন, কেন DutyFree Shop BD আলাদা এবং কীভাবে অর্ডার করবেন।',
      'meta_title'=>'DutyFree Shop BD – Premium Imported Products in Bangladesh | Blog',
      'meta_description'=>'Premium imported cigarettes and duty free products in Bangladesh. Popular brands, why customers trust us and how to order.',
      'body'=><<<'H'
<p>বাংলাদেশে এখন অনেকেই premium imported products খুঁজছেন, বিশেষ করে international cigarette brand এবং duty free quality products। কিন্তু বাজারে অনেক সময় authentic পণ্য খুঁজে পাওয়া কঠিন হয়ে যায়।</p>
<p>এই সমস্যার সমাধান হিসেবে DutyFree Shop BD একটি নির্ভরযোগ্য online platform যেখানে আপনি সহজেই premium imported cigarette এবং অন্যান্য international product অর্ডার করতে পারেন। আমাদের লক্ষ্য হলো বাংলাদেশে duty free shopping experience অনলাইনে সহজ করে দেওয়া।</p>
<h2>Why DutyFree Shop BD is Unique in Bangladesh</h2>
<h3>1. Premium Imported Products</h3>
<p>আমরা শুধুমাত্র international quality product নিয়ে কাজ করি। এখানে পাওয়া যায় বিশ্ববিখ্যাত cigarette brand যেমন: Marlboro, Manchester, Mevius, Dunhill, Mond। এই সব পণ্য duty free standard বজায় রেখে সরবরাহ করা হয়।</p>
<h2>Popular Cigarette Brands Available</h2>
<h3>Marlboro – The World's Most Famous Cigarette</h3>
<p>Marlboro বিশ্বের অন্যতম জনপ্রিয় cigarette brand। Premium tobacco blend এবং strong taste এর কারণে Marlboro smokers দের কাছে অনেক প্রিয়। Popular variants: Marlboro Red, Marlboro Gold, Marlboro Ice Blast।</p>
<h3>Manchester – Premium UK Tobacco</h3>
<p>Manchester একটি UK-based cigarette brand যা smooth smoking experience এর জন্য পরিচিত। Balanced tobacco flavour, elegant packaging এবং smooth smoke এর জন্য এটি imported cigarette lovers দের কাছে জনপ্রিয়।</p>
<h3>Mevius – Japanese Smooth Cigarette</h3>
<p>Japan এর famous cigarette brand Mevius। এই ব্র্যান্ডের বিশেষত্ব হলো charcoal filter technology যা smoke-কে আরও smooth করে। Popular variants: Mevius Wind Blue, Mevius Option।</p>
<h3>Dunhill – Luxury Smoking Experience</h3>
<p>Luxury cigarette category-তে Dunhill একটি বিখ্যাত নাম। Rich tobacco blend, luxury packaging এবং smooth smoking experience এর জন্য duty free market-এ Dunhill premium brand হিসেবে পরিচিত।</p>
<h3>Mond Superslims – Flavoured Cigarette</h3>
<p>Flavoured cigarette হিসেবে Mond খুব জনপ্রিয়। বিশেষ করে Green Apple flavour smokers দের মধ্যে বেশ পছন্দের — unique flavour, slim design এবং smooth taste।</p>
<h2>Why Imported Cigarettes Are Popular</h2>
<ul><li><b>Better Tobacco Quality:</b> Imported cigarette এ সাধারণত high quality tobacco ব্যবহার করা হয়।</li><li><b>Advanced Filter Technology:</b> অনেক premium brand charcoal filter বা advanced filter ব্যবহার করে।</li><li><b>Premium Packaging:</b> International brand গুলো সাধারণত premium design এবং packaging ব্যবহার করে।</li></ul>
<h2>Online Shopping Experience</h2>
<p>আমাদের সাইট এমনভাবে ডিজাইন করা হয়েছে যাতে customer সহজেই product খুঁজে পায় এবং order করতে পারে: easy online order system, premium imported brands, fast delivery service এবং secure ordering process।</p>
<h2>How to Order</h2>
<ol><li>Website visit করুন: [[site]]</li><li>আপনার পছন্দের product নির্বাচন করুন।</li><li>Order confirm করুন এবং delivery address দিন।</li><li>Fast delivery service এর মাধ্যমে আপনার product পৌঁছে যাবে।</li></ol>
<h2>Why Customers Trust Us</h2>
<ul><li>Authentic imported products</li><li>Easy ordering system</li><li>Reliable service</li><li>Premium brands collection</li></ul>
<h2>Conclusion</h2>
<p>বাংলাদেশে premium imported cigarette খুঁজছেন? তাহলে DutyFree Shop BD হতে পারে আপনার জন্য সেরা online destination। বিশ্ববিখ্যাত brand যেমন Marlboro, Manchester, Mevius, Dunhill এবং Mond সহজেই অর্ডার করতে আজই ভিজিট করুন: [[site]]</p>
H,
      'active'=>true, 'published_at'=>$now, 'created_at'=>$now, 'updated_at'=>$now]);
  }
  public function down(): void {}
};
