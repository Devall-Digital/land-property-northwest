<?php
/**
 * Static HTML page bodies for WordPress pages (setup scripts insert post_content).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Marketing and legal page HTML returned for insertion into page post_content.
 */
class LPNW_Page_Content {

	/**
	 * Home page: hero, steps, live feed shortcodes, pricing teaser, CTA.
	 *
	 * @return string HTML (shortcodes preserved for WordPress rendering).
	 */
	public static function get_home_content(): string {
		$register = esc_url( wp_registration_url() );
		$pricing  = esc_url( home_url( '/pricing/' ) );
		$shop_url = esc_url( home_url( '/shop/' ) );
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop_page = wc_get_page_permalink( 'shop' );
			if ( $shop_page ) {
				$shop_url = esc_url( $shop_page );
			}
		}

		return <<<HTML
<section class="lpnw-hero" aria-labelledby="lpnw-hero-heading">
	<h1 id="lpnw-hero-heading">Get NW property alerts before anyone else</h1>
	<p>We monitor planning, portals, auctions, EPCs, and Land Registry releases across Northwest England. When something matches your criteria, you get alerted fast so you can act while others are still searching manually.</p>
	<a class="lpnw-btn lpnw-btn--primary" href="{$register}">Start free</a>
</section>

<section class="lpnw-trust-bar" aria-label="Data sources we monitor">
	<p class="lpnw-trust-bar__text">Monitoring Rightmove, Zoopla, OnTheMarket, planning portals, auction houses, the EPC register, and HM Land Registry.</p>
</section>

<section class="lpnw-stats-bar" aria-labelledby="lpnw-stats-bar-title">
	<h2 id="lpnw-stats-bar-title" class="screen-reader-text">Coverage and update frequency</h2>
	<ul class="lpnw-stats-bar__list" role="list">
		<li><span class="lpnw-stats-bar__value">[lpnw_property_count]</span> properties tracked</li>
		<li><span class="lpnw-stats-bar__value">16</span> NW postcode districts covered</li>
		<li>Listing checks and alert runs every <span class="lpnw-stats-bar__value">15 minutes</span></li>
	</ul>
</section>

<section class="lpnw-how-it-works" id="lpnw-how-it-works" aria-labelledby="lpnw-how-it-works-title">
	<h2 id="lpnw-how-it-works-title" class="lpnw-how-it-works__title">How it works</h2>
	<div class="lpnw-steps">
		<div class="lpnw-step">
			<div class="lpnw-step__number" aria-hidden="true">1</div>
			<h3 id="lpnw-how-it-works-step-1" class="lpnw-step__title">We watch the whole region</h3>
			<p class="lpnw-step__text">Our systems pull from planning data, auction catalogues, the EPC register, and HM Land Registry releases, focused on Northwest England only. You get one pipeline instead of a dozen browser tabs.</p>
		</div>
		<div class="lpnw-step">
			<div class="lpnw-step__number" aria-hidden="true">2</div>
			<h3 id="lpnw-how-it-works-step-2" class="lpnw-step__title">You set your criteria</h3>
			<p class="lpnw-step__text">Choose areas, price bands, property types, and which sources you care about. Paid plans unlock full filtering so you only see deals you would actually pursue.</p>
		</div>
		<div class="lpnw-step">
			<div class="lpnw-step__number" aria-hidden="true">3</div>
			<h3 id="lpnw-how-it-works-step-3" class="lpnw-step__title">You get the alert</h3>
			<p class="lpnw-step__text">When a new record matches, we email you. Pro and VIP send instant or daily alerts. Free accounts get a weekly digest so you can judge quality before you upgrade.</p>
		</div>
	</div>
</section>

<section class="lpnw-home-feed" aria-labelledby="lpnw-home-feed-title">
	<h2 id="lpnw-home-feed-title" class="lpnw-pricing-section__title">Latest activity</h2>
	<p>A live sample of six recent records from our normalised Northwest database.</p>
	<div class="lpnw-home-feed__properties">
		[lpnw_latest_properties limit="6"]
	</div>
</section>

<section class="lpnw-pricing-section lpnw-home-pricing-teaser" id="lpnw-home-pricing" aria-labelledby="lpnw-home-pricing-title">
	<h2 id="lpnw-home-pricing-title" class="lpnw-pricing-section__title">Simple plans</h2>
	<p class="lpnw-home-pricing-teaser__lead">Start on the weekly digest, upgrade when you want instant alerts and full control. <a href="{$pricing}">Full comparison and FAQ on the pricing page</a>.</p>
	<div class="lpnw-pricing">
		<article class="lpnw-pricing-card" aria-labelledby="lpnw-home-tier-free">
			<h3 id="lpnw-home-tier-free" class="lpnw-pricing-card__name">Free</h3>
			<p class="lpnw-pricing-card__price">&pound;0</p>
			<p class="lpnw-pricing-card__period">forever</p>
			<ul class="lpnw-pricing-card__features" role="list">
				<li>Weekly digest email</li>
				<li>Sample across data sources</li>
				<li>Upgrade any time</li>
			</ul>
			<a class="lpnw-btn lpnw-btn--secondary" href="{$register}">Sign up free</a>
		</article>
		<article class="lpnw-pricing-card lpnw-pricing-card--featured" aria-labelledby="lpnw-home-tier-pro">
			<h3 id="lpnw-home-tier-pro" class="lpnw-pricing-card__name">Pro</h3>
			<p class="lpnw-pricing-card__price">&pound;19.99</p>
			<p class="lpnw-pricing-card__period">per month</p>
			<ul class="lpnw-pricing-card__features" role="list">
				<li>Instant alerts when you want them</li>
				<li>Full filters by area, price, and type</li>
				<li>Dashboard and saved properties</li>
			</ul>
			<a class="lpnw-btn lpnw-btn--primary" href="{$shop_url}">Get Pro</a>
		</article>
		<article class="lpnw-pricing-card" aria-labelledby="lpnw-home-tier-vip">
			<h3 id="lpnw-home-tier-vip" class="lpnw-pricing-card__name">VIP</h3>
			<p class="lpnw-pricing-card__price">&pound;79.99</p>
			<p class="lpnw-pricing-card__period">per month</p>
			<ul class="lpnw-pricing-card__features" role="list">
				<li>Priority delivery ahead of Pro</li>
				<li>Off-market style deal alerts</li>
				<li>Monthly report and introductions</li>
			</ul>
			<a class="lpnw-btn lpnw-btn--secondary" href="{$shop_url}">Get VIP</a>
		</article>
	</div>
	<p class="lpnw-home-pricing-teaser__foot"><a class="lpnw-btn lpnw-btn--secondary" href="{$pricing}">Compare all features</a></p>
</section>

<aside class="lpnw-cta-banner" aria-labelledby="lpnw-home-cta-heading">
	<h2 id="lpnw-home-cta-heading">Get the next deal in your inbox first</h2>
	<p>Create a free account in a minute, set your areas and alert types, and see Northwest opportunities as soon as we match them.</p>
	<a class="lpnw-btn lpnw-btn--primary" href="{$register}">Create your free account</a>
</aside>
HTML;
	}

	/**
	 * About page (~400 words).
	 *
	 * @return string HTML.
	 */
	public static function get_about_content(): string {
		$site     = esc_html( get_bloginfo( 'name' ) );
		$pricing  = esc_url( home_url( '/pricing/' ) );
		$contact  = esc_url( home_url( '/contact/' ) );

		return <<<HTML
<div class="lpnw-page-about">

<h1>About {$site}</h1>

<p>The Northwest property market rewards people who spot signals early. A planning application in Warrington, a new EPC on a refurbishment in Liverpool, a lot in an auction catalogue covering Manchester stock, a Land Registry completion on a parcel you have been tracking. Each piece is public, but almost nobody has time to check every source every morning.</p>

<p>Across Greater Manchester, Merseyside, Lancashire, Cheshire, and Cumbria, rental demand in the core cities, industrial and logistics appetite along key corridors, and residential infill where consent is the bottleneck still produce workable deals. The question is rarely whether opportunity exists, but whether you hear about it in time to act.</p>

<p>{$site} exists to close that gap. We aggregate data that serious buyers usually chase across separate websites: planning applications (national planning data and local portals), Energy Performance Certificate filings, HM Land Registry Price Paid Data, and auction houses that regularly catalogue Northwest lots. We normalise addresses, postcodes, and source metadata into one feed, then match records to the rules you set.</p>

<p>Nobody else packages that combination for subscribers who only care about Northwest England. National dashboards dilute the signal. Single-source tools miss the joins between planning, auctions, EPCs, and completions. We built this because investors and developers asked for speed and coverage in one place, not another generic property email.</p>

<h2>Who it is for</h2>

<p><strong>Investors and developers</strong> who want the earliest legitimate lead on land, lots, and conversions. <strong>Estate and letting agents</strong> tracking instructions and comparables before competitors pitch the same vendor. <strong>Surveyors, architects, contractors, and other professionals</strong> who already use planning and transaction flow in their work. If your edge is getting to the phone first, you are the audience.</p>

<h2>How we work</h2>

<p>We focus on data we can ingest reliably from public or open sources, surface the original reference where possible, and improve parsers when councils or providers change format. We are not a substitute for solicitors, surveyors, or your own underwriting. We speed up discovery; due diligence stays with you.</p>

<p>You can start on a free weekly digest, then move to paid plans when you want instant alerts and full filters. Questions about coverage or accounts? Use our contact form.</p>

<p><a class="lpnw-btn lpnw-btn--primary" href="{$pricing}">View pricing</a> <a class="lpnw-btn lpnw-btn--secondary" href="{$contact}">Contact us</a></p>

</div>
HTML;
	}

	/**
	 * Pricing page: comparison table and FAQ.
	 *
	 * @return string HTML.
	 */
	public static function get_pricing_content(): string {
		$register = esc_url( wp_registration_url() );
		$shop_url = esc_url( home_url( '/shop/' ) );
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop_page = wc_get_page_permalink( 'shop' );
			if ( $shop_page ) {
				$shop_url = esc_url( $shop_page );
			}
		}

		return <<<HTML
<section class="lpnw-hero" aria-labelledby="lpnw-pricing-hero-heading">
	<h1 id="lpnw-pricing-hero-heading">Pricing</h1>
	<p>Free for a taste of the feed, Pro for full automation, VIP when you want priority delivery and extra intelligence on top.</p>
</section>

<section class="lpnw-pricing-section" id="lpnw-pricing-compare" aria-labelledby="lpnw-pricing-compare-title">
	<h2 id="lpnw-pricing-compare-title" class="lpnw-pricing-section__title">Compare plans</h2>
	<div class="lpnw-pricing-table-wrap">
		<table class="lpnw-pricing-table">
			<thead>
				<tr>
					<th scope="col">Feature</th>
					<th scope="col">Free</th>
					<th scope="col">Pro (£19.99/mo)</th>
					<th scope="col">VIP (£79.99/mo)</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<th scope="row">Alerts</th>
					<td>Weekly digest</td>
					<td>Instant alerts</td>
					<td>Instant alerts, about 30 minutes before Pro on new matches</td>
				</tr>
				<tr>
					<th scope="row">History</th>
					<td>Limited history</td>
					<td>Full history in the dashboard</td>
					<td>Full history</td>
				</tr>
				<tr>
					<th scope="row">Email</th>
					<td>Basic email</td>
					<td>Full alert detail</td>
					<td>Full alert detail, priority queue</td>
				</tr>
				<tr>
					<th scope="row">Filtering</th>
					<td>Basic</td>
					<td>Full filtering by area, price, and type</td>
					<td>Same as Pro</td>
				</tr>
				<tr>
					<th scope="row">Data sources</th>
					<td>Sample across sources in the digest</td>
					<td>Planning, auction, EPC, and Land Registry alerts</td>
					<td>Everything in Pro, plus off-market deal alerts where we surface them</td>
				</tr>
				<tr>
					<th scope="row">Dashboard and saves</th>
					<td>No</td>
					<td>Dashboard, saved properties</td>
					<td>Dashboard, saved properties</td>
				</tr>
				<tr>
					<th scope="row">Frequency</th>
					<td>Weekly only</td>
					<td>Daily or instant</td>
					<td>Daily or instant</td>
				</tr>
				<tr>
					<th scope="row">Extras</th>
					<td>Upgrade any time</td>
					<td>Standard support</td>
					<td>Monthly market report, direct introductions where we can make them</td>
				</tr>
			</tbody>
		</table>
	</div>
	<div class="lpnw-pricing">
		<article class="lpnw-pricing-card" aria-labelledby="lpnw-pricing-tier-free">
			<h3 id="lpnw-pricing-tier-free" class="lpnw-pricing-card__name">Free</h3>
			<p class="lpnw-pricing-card__price">£0</p>
			<p class="lpnw-pricing-card__period">forever</p>
			<ul class="lpnw-pricing-card__features" role="list">
				<li>Weekly digest</li>
				<li>Limited history</li>
				<li>Basic email</li>
			</ul>
			<a class="lpnw-btn lpnw-btn--secondary" href="{$register}">Sign up free</a>
		</article>
		<article class="lpnw-pricing-card lpnw-pricing-card--featured" aria-labelledby="lpnw-pricing-tier-pro">
			<h3 id="lpnw-pricing-tier-pro" class="lpnw-pricing-card__name">Pro</h3>
			<p class="lpnw-pricing-card__price">£19.99</p>
			<p class="lpnw-pricing-card__period">per month</p>
			<ul class="lpnw-pricing-card__features" role="list">
				<li>Instant alerts</li>
				<li>Full filtering by area, price, and type</li>
				<li>Planning, auction, EPC, and Land Registry alerts</li>
				<li>Dashboard and saved properties</li>
				<li>Daily or instant frequency</li>
			</ul>
			<a class="lpnw-btn lpnw-btn--primary" href="{$shop_url}">Get Pro</a>
		</article>
		<article class="lpnw-pricing-card" aria-labelledby="lpnw-pricing-tier-vip">
			<h3 id="lpnw-pricing-tier-vip" class="lpnw-pricing-card__name">VIP</h3>
			<p class="lpnw-pricing-card__price">£79.99</p>
			<p class="lpnw-pricing-card__period">per month</p>
			<ul class="lpnw-pricing-card__features" role="list">
				<li>Everything in Pro</li>
				<li>About 30 minutes priority over Pro users on new matches</li>
				<li>Off-market deal alerts</li>
				<li>Monthly market report</li>
				<li>Direct introductions</li>
			</ul>
			<a class="lpnw-btn lpnw-btn--secondary" href="{$shop_url}">Get VIP</a>
		</article>
	</div>
</section>

<section class="lpnw-how-it-works" id="lpnw-pricing-faq" aria-labelledby="lpnw-pricing-faq-title">
	<h2 id="lpnw-pricing-faq-title" class="lpnw-how-it-works__title">Frequently asked questions</h2>
	<div class="lpnw-faq">
		<h3 class="lpnw-step__title">What is the difference between Pro and VIP?</h3>
		<p class="lpnw-step__text">Both get instant alerts, full filters, and all four data sources. VIP goes out first on the queue (about 30 minutes ahead of Pro), and adds off-market style alerts, a monthly market report, and direct introductions where we can make them.</p>
		<h3 class="lpnw-step__title">Can I cancel any time?</h3>
		<p class="lpnw-step__text">Yes. Cancel from your account or via the link in your subscription emails. You keep access until the end of the period you already paid for.</p>
		<h3 class="lpnw-step__title">How does billing work?</h3>
		<p class="lpnw-step__text">Paid plans renew automatically each month until you cancel. Card payments run through Stripe via WooCommerce. We do not store your full card number on our server.</p>
		<h3 class="lpnw-step__title">Is the underlying data the same on every tier?</h3>
		<p class="lpnw-step__text">The same pipeline powers every tier. Free gives you a weekly digest with limited history. Pro and VIP unlock instant delivery, full history in the dashboard, saved properties, and tighter filters.</p>
		<h3 class="lpnw-step__title">Do you offer refunds?</h3>
		<p class="lpnw-step__text">Subscription terms at checkout apply. If something fails on our side, contact us and we will put it right or offer a fair credit.</p>
		<h3 class="lpnw-step__title">Can my team share one login?</h3>
		<p class="lpnw-step__text">Accounts are per subscriber. If you need several seats for a firm, email us and we will suggest the cleanest setup.</p>
	</div>
</section>
HTML;
	}

	/**
	 * Contact page.
	 *
	 * @return string HTML.
	 */
	public static function get_contact_content(): string {
		return <<<'HTML'
<div class="lpnw-page-contact">

<h1>Contact</h1>

<p>For billing queries, technical issues, data questions, or partnership enquiries, use the form below. We aim to reply within two working days. If you already have an account, include the email on your subscription so we can find you quickly.</p>

[lpnw_contact_form]

</div>
HTML;
	}

	/**
	 * Privacy policy (sole trader, UK GDPR).
	 *
	 * @return string HTML.
	 */
	public static function get_privacy_content(): string {
		$site_name = esc_html( get_bloginfo( 'name' ) );
		$site_url  = esc_url( home_url( '/' ) );
		$contact   = esc_url( home_url( '/contact/' ) );

		return <<<HTML
<div class="lpnw-page-legal">

<h1>Privacy policy</h1>
<p><strong>Last updated:</strong> 1 April 2026</p>

<p>{$site_name} ("we", "us", "our") operates {$site_url}. This policy describes how we collect, use, and store personal data when you use the site, register, or subscribe. The business is operated as a <strong>sole trader</strong> based in England. For UK GDPR and the Data Protection Act 2018, we are the data controller.</p>

<h2>What data we collect</h2>
<ul>
	<li><strong>Identity and contact:</strong> name and email address when you register, subscribe, or contact us.</li>
	<li><strong>Alert preferences:</strong> areas, postcodes, price ranges, property types, alert frequency, saved properties, and similar settings stored in your account.</li>
	<li><strong>Account and billing identifiers:</strong> WordPress user ID, subscription status, and transaction references. Card numbers are processed by Stripe; we do not hold your full card details on our server.</li>
	<li><strong>Technical data:</strong> IP address, browser type, timestamps, and similar data in server logs and security tools (for example Wordfence).</li>
</ul>

<h2>Why we use your data</h2>
<p>We use personal data to provide the service: matching public property records to your criteria and sending email alerts. We also use it to manage your account, take payments, respond to enquiries, comply with law, and protect the site from abuse.</p>
<p>Lawful bases include performance of a contract (subscriptions and registered accounts), legitimate interests (operating and securing the platform, limited analytics), and consent where we ask for it clearly (for example optional marketing).</p>

<h2>How we store it</h2>
<p>Account and preference data are stored in the WordPress database on <strong>UK-based hosting</strong> (20i). Access is restricted to what is needed to run the site. Backups may be held by our hosting or backup provider under their terms.</p>

<h2>Third parties</h2>
<ul>
	<li><strong>Stripe</strong> processes card payments for WooCommerce. Stripe receives the payment details you enter at checkout.</li>
	<li><strong>Mautic</strong> (marketing.land-property-northwest.co.uk) sends transactional and marketing emails and may record engagement metrics (for example opens or clicks) according to your settings and our configuration.</li>
	<li><strong>Hosting, email transport, and security vendors</strong> process data only as needed to deliver the service.</li>
</ul>
<p>We use processors under terms that require them to protect personal data appropriately.</p>

<h2>Cookies</h2>
<p>WordPress sets cookies to keep you logged in when you use an account. WooCommerce may set cookies for cart and checkout. A cookie notice plugin may store your consent choice. You can control cookies through your browser settings.</p>

<h2>Retention</h2>
<p>We keep account and billing records while your relationship is active and for a period afterwards where the law requires (for example tax or dispute resolution). You can ask for erasure sooner where the law allows.</p>

<h2>Your rights</h2>
<p>Under UK GDPR you may have the right to access, rectify, erase, restrict, or object to processing of your personal data, and in some cases the right to data portability. You may lodge a complaint with the <strong>Information Commissioner's Office (ICO)</strong>. We encourage you to contact us first so we can resolve issues quickly.</p>

<h2>Contact details</h2>
<p>For privacy requests or questions about this policy, use our <a href="{$contact}">contact page</a> or the email address shown in the site footer. You may also write to the postal address given in the footer.</p>

</div>
HTML;
	}

	/**
	 * Terms of service.
	 *
	 * @return string HTML.
	 */
	public static function get_terms_content(): string {
		$site_name   = esc_html( get_bloginfo( 'name' ) );
		$site_url    = esc_url( home_url( '/' ) );
		$privacy_id  = (int) get_option( 'wp_page_for_privacy_policy' );
		$privacy     = $privacy_id ? esc_url( get_permalink( $privacy_id ) ) : esc_url( home_url( '/privacy-policy/' ) );
		$contact     = esc_url( home_url( '/contact/' ) );

		return <<<HTML
<div class="lpnw-page-legal">

<h1>Terms of service</h1>
<p><strong>Last updated:</strong> 1 April 2026</p>

<p>These terms govern your use of {$site_name} at {$site_url}. By registering, subscribing, or using paid features, you agree to them. If you do not agree, do not use the service.</p>

<h2>Subscriptions</h2>
<p>Paid plans are recurring subscriptions sold through WooCommerce. Prices are as shown at checkout and include VAT where applicable. By subscribing you authorise us and our payment processor to charge your payment method on each renewal until you cancel.</p>

<h2>Auto-renewal</h2>
<p>Subscriptions renew automatically for the same term unless you cancel before the renewal date. Renewal charges use the payment method you provided unless you update it.</p>

<h2>Cancellation</h2>
<p>You may <strong>cancel at any time</strong> from your account area or using the instructions in your receipt or subscription emails. Cancellation stops future charges. You normally retain access until the end of the period you have already paid for, unless we tell you otherwise at purchase.</p>

<h2>Accuracy and disclaimer</h2>
<p>We aggregate <strong>public and open data</strong> from third-party sources. We do not guarantee that every record is complete, timely, or error-free. Alerts are for information only. Nothing on this site is financial, investment, tax, or legal advice. You must carry out your own due diligence and take professional advice where appropriate.</p>

<h2>Acceptable use</h2>
<p>You must not misuse the service: for example by attempting to bypass access controls, overloading our systems, reselling raw alert feeds without agreement, or using the site for unlawful purposes. We may suspend or close accounts that breach these terms.</p>

<h2>Limitation of liability</h2>
<p>To the fullest extent permitted by law, we exclude liability for indirect or consequential loss, loss of profit, or loss of opportunity arising from your use of the service or reliance on alert content. Our total liability relating to these terms is capped at the fees you paid us in the twelve months before the claim, except where English law does not allow such a limit.</p>

<h2>Privacy</h2>
<p>Our use of personal data is described in the <a href="{$privacy}">privacy policy</a>.</p>

<h2>Governing law</h2>
<p>These terms are governed by the laws of <strong>England and Wales</strong>. The courts of England and Wales have exclusive jurisdiction, subject to any mandatory consumer rights that apply where you live.</p>

<h2>Changes</h2>
<p>We may update these terms by posting a new version on the site. Where changes materially affect paid subscribers, we will give notice by email or through your account where appropriate.</p>

<h2>Contact</h2>
<p>Questions about these terms: <a href="{$contact}">contact us</a>.</p>

</div>
HTML;
	}
}
