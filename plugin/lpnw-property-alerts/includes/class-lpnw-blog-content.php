<?php
/**
 * Static blog post bodies for marketing and SEO (import or render via theme).
 *
 * @package LPNW_Property_Alerts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Curated Northwest England property blog content.
 */
class LPNW_Blog_Content {

	/**
	 * Category definitions (name and slug).
	 *
	 * @return array<int, array{name: string, slug: string}>
	 */
	public static function get_categories(): array {
		return array(
			array(
				'name' => 'Guides',
				'slug' => 'guides',
			),
			array(
				'name' => 'Market analysis',
				'slug' => 'market-analysis',
			),
			array(
				'name' => 'Planning',
				'slug' => 'planning',
			),
			array(
				'name' => 'Auctions',
				'slug' => 'auctions',
			),
			array(
				'name' => 'Area profiles',
				'slug' => 'area-profiles',
			),
		);
	}

	/**
	 * All curated posts in display order.
	 *
	 * @return array<int, array{title: string, slug: string, content: string, excerpt: string, category: string}>
	 */
	public static function get_posts(): array {
		return array(
			self::post_northwest_investment_region(),
			self::post_auction_buying_guide(),
			self::post_gm_planning_tracking(),
			self::post_epc_investors(),
			self::post_lancashire_land(),
			self::post_permitted_development(),
			self::post_market_trends_2026(),
			self::post_bmv_liverpool(),
			self::post_section_106(),
			self::post_auction_house_guide(),
		);
	}

	/**
	 * @return array{title: string, slug: string, content: string, excerpt: string, category: string}
	 */
	private static function post_northwest_investment_region(): array {
		return array(
			'title'    => 'Why Northwest England Is One of the Best Property Investment Regions in the UK',
			'slug'     => 'northwest-england-best-property-investment-region-uk',
			'excerpt'  => 'From Manchester and Liverpool cores to Lancashire corridors and Cheshire logistics, the Northwest still stacks up on yield, refurbishment angles, and development land. Here is how the data backs that up.',
			'category' => 'market-analysis',
			'content'  => self::content_northwest_investment_region(),
		);
	}

	/**
	 * @return array{title: string, slug: string, content: string, excerpt: string, category: string}
	 */
	private static function post_auction_buying_guide(): array {
		return array(
			'title'    => 'A Complete Guide to Buying Property at Auction in the Northwest',
			'slug'     => 'complete-guide-buying-property-auction-northwest',
			'excerpt'  => 'Auction stock in the Northwest runs from city terraces in L8 and M14 through industrial units near Warrington and land on the Fylde coast. This guide covers legals, surveys, and how to bid with your eyes open.',
			'category' => 'guides',
			'content'  => self::content_auction_buying_guide(),
		);
	}

	/**
	 * @return array{title: string, slug: string, content: string, excerpt: string, category: string}
	 */
	private static function post_gm_planning_tracking(): array {
		return array(
			'title'    => 'How to Track Planning Applications in Greater Manchester',
			'slug'     => 'track-planning-applications-greater-manchester',
			'excerpt'  => 'Greater Manchester splits across ten districts, each with its own portal, but national planning data on planning.data.gov.uk still helps you see the wood for the trees.',
			'category' => 'planning',
			'content'  => self::content_gm_planning_tracking(),
		);
	}

	/**
	 * @return array{title: string, slug: string, content: string, excerpt: string, category: string}
	 */
	private static function post_epc_investors(): array {
		return array(
			'title'    => 'Understanding EPC Ratings: What Property Investors Need to Know',
			'slug'     => 'epc-ratings-property-investors-guide',
			'excerpt'  => 'A new or updated EPC often appears before a sale or let is advertised. For investors, it is a lead indicator, not just a compliance box.',
			'category' => 'guides',
			'content'  => self::content_epc_investors(),
		);
	}

	/**
	 * @return array{title: string, slug: string, content: string, excerpt: string, category: string}
	 */
	private static function post_lancashire_land(): array {
		return array(
			'title'    => 'Land for Sale in Lancashire: Where to Look and What to Pay',
			'slug'     => 'land-sale-lancashire-where-look-what-pay',
			'excerpt'  => 'Lancashire stretches from Preston and Blackburn to Blackpool and the Ribble Valley. Land values swing sharply by use class, access, and planning hope value.',
			'category' => 'area-profiles',
			'content'  => self::content_lancashire_land(),
		);
	}

	/**
	 * @return array{title: string, slug: string, content: string, excerpt: string, category: string}
	 */
	private static function post_permitted_development(): array {
		return array(
			'title'    => 'The Property Developer\'s Guide to Permitted Development Rights',
			'slug'     => 'property-developer-guide-permitted-development-rights',
			'excerpt'  => 'PD rights can unlock extra storeys, change of use, and extensions without a full planning application, but Article 4 directions and conservation areas bite hard in parts of the Northwest.',
			'category' => 'guides',
			'content'  => self::content_permitted_development(),
		);
	}

	/**
	 * @return array{title: string, slug: string, content: string, excerpt: string, category: string}
	 */
	private static function post_market_trends_2026(): array {
		return array(
			'title'    => 'Northwest Property Market: Key Trends for 2026',
			'slug'     => 'northwest-property-market-trends-2026',
			'excerpt'  => 'Rates have settled, repricing has largely washed through, and the region is back to deals driven by stock type, micro location, and planning upside.',
			'category' => 'market-analysis',
			'content'  => self::content_market_trends_2026(),
		);
	}

	/**
	 * @return array{title: string, slug: string, content: string, excerpt: string, category: string}
	 */
	private static function post_bmv_liverpool(): array {
		return array(
			'title'    => 'How to Find Below Market Value Properties in Liverpool and Merseyside',
			'slug'     => 'below-market-value-properties-liverpool-merseyside',
			'excerpt'  => 'BMV is not a postcode lottery. It is usually stress, probate, tired stock, or a seller who values speed. Merseyside gives you all three if you know where to look.',
			'category' => 'area-profiles',
			'content'  => self::content_bmv_liverpool(),
		);
	}

	/**
	 * @return array{title: string, slug: string, content: string, excerpt: string, category: string}
	 */
	private static function post_section_106(): array {
		return array(
			'title'    => 'Section 106 Agreements Explained: What Developers Need to Know',
			'slug'     => 'section-106-agreements-developers-guide',
			'excerpt'  => 'A Section 106 obligation can make or break scheme viability. In the Northwest, affordable housing, highways, and education contributions still dominate negotiations with councils from Wirral to Carlisle.',
			'category' => 'planning',
			'content'  => self::content_section_106(),
		);
	}

	/**
	 * @return array{title: string, slug: string, content: string, excerpt: string, category: string}
	 */
	private static function post_auction_house_guide(): array {
		return array(
			'title'    => 'Auction House Guide: Every Property Auction in the Northwest',
			'slug'     => 'auction-house-guide-northwest-property-auctions',
			'excerpt'  => 'Serious buyers bookmark more than one catalogue. Pugh, SDL Property Auctions, Allsop, and Auction House North West regularly list stock across M, L, CH, WA, and FY postcodes.',
			'category' => 'auctions',
			'content'  => self::content_auction_house_guide(),
		);
	}

	/**
	 * @return string HTML post body.
	 */
	private static function content_northwest_investment_region(): string {
		return <<<'HTML'
<p>If you strip out the national headlines and look at what actually trades, Northwest England still offers a rare mix: two major city economies, deep commuter belts, industrial and logistics corridors, and coast and countryside within an hour. Investors care about yield, growth of income, and the cost of entry. On those measures, Manchester, Liverpool, and their hinterlands continue to compete with London commuter zones without the same stamp duty friction on many lot sizes.</p>

<h2>City cores and inner suburbs</h2>

<p>Greater Manchester and Liverpool city regions absorb most institutional and serious private capital. Postcode districts such as M14, M20, L15, and L17 have long track records for family housing and student adjacency, which supports both buy to let and refurb to sell. Closer to the centre, you trade yield for growth, and vice versa, but liquidity is usually better than in smaller regional cities.</p>

<h2>Lancashire, Cheshire, and Warrington</h2>

<p>Preston (PR), Blackburn (BB), and the Fylde (FY) give you lower entry points and often stronger headline yields, but voids and management intensity can rise if you buy the wrong street. Cheshire and Warrington (WA) sit on strategic road and rail links; logistics and employment land around M56 and M6 corridors still underpin residential demand in nearby towns.</p>

<h2>Cumbria and the northern edge</h2>

<p>Carlisle (CA) and the West Coast Main Line corridor give you a different risk return profile from the M62 belt. Holiday letting pressure around the Lake District interacts with strict planning policy, while Carlisle itself behaves more like a small regional hub with public sector and distribution employment. Do not assume CA postcodes move in lockstep with M or L; your buyer pool and refinance evidence are different.</p>

<h2>Students, young professionals, and churn</h2>

<p>Large student populations in Manchester, Liverpool, and Lancaster support HMO and small flat strategies when licensing and standards are respected. The money is often made on refurb and operational efficiency, not on expecting nominal house price growth every year. When you underwrite, stress test rents against local median wages, not against the best room rate you saw on a portal last summer.</p>

<h2>What the public data shows</h2>

<p>HM Land Registry Price Paid Data will not tell you about off market deals, but it will anchor your view of what actually completed, street by street, month by month. The monthly open data files are free to download and slice by postcode district, which is how professionals build their own comp sheets instead of trusting a single portal estimate. Pair that with planning applications discoverable through <a href="https://www.planning.data.gov.uk/" rel="noopener noreferrer">planning.data.gov.uk</a> and you start to see where consent activity is clustering before agents push new stock hard.</p>

<p>Energy Performance Certificate data, published as open data, is another early signal. A fresh EPC on a tired terrace in Toxteth or Oldham often precedes a sale or refinance. It is not definitive, but it is a useful tripwire when you are scanning at scale.</p>

<p>Auction catalogues from established houses also tell you where lenders, estates, and motivated sellers are liquidating stock. In the Northwest those lines often land before the same asset is marketed conventionally, which is why serious buyers monitor auctions even when they prefer private treaty completion.</p>

<h2>Risks that are real, not theoretical</h2>

<p>Selective licensing, Article 4 directions, and building safety rules can change your numbers after you own the asset. Flood risk on parts of the Mersey corridor, and coastal exposure near Blackpool, need proper searches, not a glance at a portal photo. The Northwest is not a single market. Treat SK postcodes differently from CH, and M60 belt towns differently from central M1.</p>

<h2>Why speed still matters</h2>

<p>The best small development sites and auction lots still go to whoever sees the instruction or the legal pack first. If you want to be in that queue without refreshing ten websites, you can set up property alerts on Land &amp; Property Northwest and get notified when new records hit our Northwest coverage.</p>
HTML;
	}

	/**
	 * @return string HTML post body.
	 */
	private static function content_auction_buying_guide(): string {
		return <<<'HTML'
<p>Buying at auction in the Northwest is straightforward on paper: register, transfer your deposit, bid, complete within the legal deadline. In practice, the work happens before the gavel. The catalogues from Pugh, SDL Property Auctions, Allsop, and Auction House North West regularly include terraced housing in Liverpool and Manchester, mixed commercial in towns such as Bolton and Stockport, and land on the edges of Preston or Lancaster.</p>

<h2>Modern method and unconditional sales</h2>

<p>Modern method of auction often gives a reservation period after the gavel for the buyer to organise mortgage finance, but the legal pack still matters and fees differ from unconditional sales. Traditional room auctions and timed online auctions may be unconditional on the fall of the hammer. Mis read that distinction and your deposit is at risk. The catalogue front sheet and special conditions state which regime applies; screenshot them when you first read the pack in case the website updates.</p>

<h2>Guide prices and reserve</h2>

<p>Guide price is marketing, not a valuation. The reserve can sit anywhere the seller chooses within auctioneer norms. Use your own comps from HM Land Registry Price Paid Data for the street and adjoining streets, then adjust for condition and legal risk. If you cannot explain your maximum bid on a single sheet of assumptions, you are gambling.</p>

<h2>Read the legal pack first</h2>

<p>Assume nothing. The special conditions can override the general conditions of sale. Look for overage, clawback, restrictive covenants, and anything that makes title defective until remedied. If the property is tenanted, check the tenancy schedule and deposit protection. If it is vacant, check utilities, council tax, and whether the seller has cleared rates.</p>

<h2>Surveys and viewings</h2>

<p>Many lots are sold as seen. A drive past on Google Street View is not due diligence. If the auctioneer offers open days, use them. For structural risk on older stock, budget for a surveyor who understands auction timetables. You will not always get a full building survey back before bidding, but you can often get enough to know what you are gambling on.</p>

<h2>Finance and completion</h2>

<p>Cash is king at auction. If you need a bridge, agree terms with your lender before you bid, not after. Miss the completion date and you forfeit deposit plus exposure to seller claims. Work backwards from the completion day in the catalogue and build in slack for your solicitor.</p>

<h2>Tenanted stock and regulated tenancies</h2>

<p>Buyers chasing yield sometimes bid on sitting tenants without reading the tenancy type. Regulated or assured shorthold tenancies have different notice and possession routes. Rent schedules in the pack should match bank statements in due diligence where possible. If the seller cannot prove rent, your lender may down value even when the hammer price looked cheap.</p>

<h2>Northwest specifics</h2>

<p>In Merseyside and parts of Greater Manchester, check selective licensing and HMO rules before you model rent. In former industrial buildings, contamination and planning use class need a professional eye. Land lots may look cheap on a per acre basis until you discover access is only by informal track or the site sits outside developable boundaries in the local plan.</p>

<h2>Using alerts to watch new lots</h2>

<p>Catalogues drop on irregular cycles. If you are targeting specific postcodes or price bands, set up property alerts on Land &amp; Property Northwest so new auction lines appear in your inbox alongside other sources we track.</p>

<h2>After the hammer falls</h2>

<p>Winning is only the start. Your solicitor exchanges contracts on the auction timetable, not when you feel ready. Insure the building from exchange if the risk passes to you immediately. Arrange meter reads, council tax liability, and notify any tenants through the proper channels if the pack requires it. Keep a single email thread with your lawyer and paste the catalogue lot number in every subject line so nothing gets lost when they are juggling twenty files.</p>
HTML;
	}

	/**
	 * @return string HTML post body.
	 */
	private static function content_gm_planning_tracking(): string {
		return <<<'HTML'
<p>Greater Manchester is not one planning authority. Bolton, Bury, Manchester, Oldham, Rochdale, Salford, Stockport, Tameside, Trafford, and Wigan each run their own committee calendars, validation rules, and online portals. That fragmentation trips up anyone who expects a single login to cover the whole conurbation.</p>

<h2>Why each district still matters</h2>

<p>Bolton and Wigan see different industrial legacy and housing mix from Trafford and Stockport. Oldham and Rochdale include steep topography and terraces where highways and parking constraints decide whether a back land scheme is viable. Tameside sits on key road links toward Yorkshire. If you are hunting infill sites, draw your search radius around employment nodes and tram or rail stations, not just a circle on a map centred on Manchester city centre.</p>

<h2>Start with national data</h2>

<p>The Planning Data platform at <a href="https://www.planning.data.gov.uk/" rel="noopener noreferrer">planning.data.gov.uk</a> aggregates planning application data from participating authorities. Filters are imperfect, but you can still narrow by area, application type, and date to spot clusters of major applications, housing allocations, and commercial schemes. Treat it as reconnaissance, then drill into the relevant district portal for drawings, officer reports, and consultation deadlines.</p>

<h2>What to track by strategy</h2>

<p>Developers care about outline and full applications, discharge of conditions, and non material amendments. Investors watch change of use to C3, HMO-related applications, and extensions that signal refurb activity. Neighbours care about enforcement and licensing. Pick the application types that correlate with your deal flow, otherwise you drown in noise.</p>

<h2>Trafford, Stockport, and the southern belt</h2>

<p>Trafford (M33, parts of M16, M17, M31, M32) and Stockport (SK postcodes) often see strong competition for well located consented schemes. Tracking appeals and called in decisions matters here because land values bake in planning probability. If you only read the weekly press release, you are late.</p>

<h2>Manchester city and Salford</h2>

<p>Manchester (M1 to M20 and beyond) and Salford (M3, M5, M6, M7, M50) carry high volumes. Use map views on local portals where available, and save searches by ward if you can. Large schemes near the Irwell corridor and at Salford Quays move through pre application advice and committee in public; those minutes are worth reading for policy tone.</p>

<h2>Bury, Bolton, and northern GM</h2>

<p>BL and parts of M25, M26, M45 postcodes fall under Bury and Bolton councils. Housing land supply and green belt release debates show up in local plan examinations before they hit <a href="https://www.planning.data.gov.uk/" rel="noopener noreferrer">planning.data.gov.uk</a> as live applications. Follow the plan period for each authority; allocation changes move land values before a single spade hits the ground.</p>

<h2>Enforcement and unauthorised works</h2>

<p>Enforcement registers matter as much as new applications. A terrace with a dubious rear extension may be a bargain or a liability. Enforcement cases are published on district portals and sometimes surface in national data extracts. Cross check the address against HM Land Registry title where you can, and budget for regularisation or removal if you buy the risk.</p>

<h2>Turning signals into action</h2>

<p>When you have a shortlist of wards or postcodes, automation beats memory. You can set up property alerts on Land &amp; Property Northwest to get notified when new planning records in our feed match your geography, so you are not dependent on checking ten council sites by hand every morning.</p>

<h2>Consultations and material considerations</h2>

<p>When you object or support an application, anchor your comments in local plan policy and material considerations. Generic rants about parking rarely help your case. Cite the site address, application reference, and the policies you think officers misapplied. If you are a professional objecting on behalf of a client, declare interest clearly. The same discipline applies when you lobby in favour of employment schemes in Rochdale or housing off the A6 corridor: evidence beats volume.</p>
HTML;
	}

	/**
	 * @return string HTML post body.
	 */
	private static function content_epc_investors(): string {
		return <<<'HTML'
<p>An Energy Performance Certificate rates how energy efficient a building is on a scale from A to G. For landlords, minimum standards now bite on new lets and some renewals. For investors, the certificate is also metadata: floor area, construction hints, heating system, and the date of assessment.</p>

<h2>Domestic versus non domestic</h2>

<p>Most residential investors deal with domestic EPCs. If you touch commercial to residential conversion, you may also need a non domestic EPC before conversion works and a new domestic certificate after. Mixed use buildings on high streets in Wigan, St Helens, or Chester often trip people up because the wrong certificate type was lodged. Your solicitor and energy assessor should confirm which regime applies before you exchange.</p>

<h2>Why investors watch EPC events</h2>

<p>When an EPC is lodged or updated on a property that has sat unmoved for years, something often changes. The owner may be preparing a sale, remortgaging, or bringing a let back to market after works. The open data release on EPC does not replace Land Registry completion data, but it can precede it. That timing edge matters in competitive postcodes.</p>

<h2>How to read the certificate critically</h2>

<p>Check the property type and floor area against the listing you expect. Errors happen. Look at recommended measures; they hint at spend to improve rating. If the roof and walls score poorly on a Victorian terrace in Liverpool or Oldham, your refurb budget needs a line for insulation and ventilation strategy, not just cosmetic kitchens.</p>

<h2>Regulation and enforcement</h2>

<p>Rules evolve. Always confirm current UK Government guidance for the private rented sector before you rely on a borderline E or F asset as a long term let. Local enforcement and licensing schemes in the Northwest can add constraints beyond the national floor. Wirral, Liverpool, and parts of Greater Manchester operate licensing zones that interact with housing condition.</p>

<h2>Retrofit costs and procurement</h2>

<p>Improving a solid wall Victorian house from an F toward a C is not just loft rolls and LED bulbs. External wall insulation, air source heat pumps, and proper ventilation design need coordinated trades. Get quotes before you assume a five thousand pound fix. If you are comparing two terraces in Burnley or Salford, the one with a recent EPC update may already have absorbed part of that cost, which changes your true purchase price.</p>

<h2>Combining EPC with other signals</h2>

<p>Use EPC alongside HM Land Registry Price Paid Data for evidence of what actually sold, and planning.data.gov.uk for consent on extensions or conversions. None of those sources alone tells the full story. Together they narrow the list of assets worth a site visit.</p>

<h2>Alerts when new certificates appear</h2>

<p>Manually searching the register weekly is tedious. If you want Northwest coverage filtered to your areas, set up property alerts on Land &amp; Property Northwest and we will surface new EPC related records alongside our other feeds when they match your criteria.</p>

<h2>Sellers, buyers, and disclosure</h2>

<p>When you sell, you must make certain domestic EPC information available in line with current rules. Buyers still need to verify that the certificate they see matches the property they viewed. Extensions built after the last assessment, loft conversions, and heating system swaps can all invalidate the story the front page tells. On probate stock in Wirral or Warrington, executors sometimes present an old certificate; budget for a fresh assessment if your lender or insurer insists.</p>

<h2>Portfolio monitoring</h2>

<p>If you hold dozens of units across Manchester and Liverpool, export EPC lodgement dates into your asset spreadsheet the same way you track rent reviews. A cluster of D ratings in one block may signal failing communal systems or poor roof insulation across the roofline. Fixing one flat while neighbours leak heat through the loft hatch helps nobody. Treat EPC data as portfolio maintenance intelligence, not just a compliance badge.</p>
HTML;
	}

	/**
	 * @return string HTML post body.
	 */
	private static function content_lancashire_land(): string {
		return <<<'HTML'
<p>Lancashire mixes dense urban wards in Preston and Blackburn with coastal towns, market towns in the Ribble Valley, and agricultural parcels toward the boundary with Cumbria and Greater Manchester. Land pricing is not published on a neat national grid. You anchor value off comparables, planning designation, and access.</p>

<h2>East Lancashire valleys</h2>

<p>Rossendale, Hyndburn, and Pendle combine former mill towns with tight valleys and pockets of strong local demand. Access roads and parking often cap density before policy does. If you are offered a plot off a single track lane near Bacup or Haslingden, model fire appliance access and adoptable highway standards before you pay option fees.</p>

<h2>Greenfield, brownfield, and garden fringe</h2>

<p>True greenfield with outline consent in a deliverable local plan position commands a premium. Brownfield close to infrastructure in Preston (PR1, PR2) or along key roads out of Blackburn (BB1, BB2) can trade on different maths: remediation cost versus speed to implement. Edge of settlement plots around Clitheroe or Longridge need a sharp eye on landscape and highways constraints even when they look logical on a map.</p>

<h2>Blackpool, Fylde, and Wyre</h2>

<p>FY postcodes cover tourism exposure, retirement housing demand, and pockets of deep deprivation within streets of each other. Land for housing near employment nodes can work; speculative holiday plots without planning are a gamble. Always check flood and coastal change evidence on Environment Agency data alongside the local planning authority maps.</p>

<h2>Evidence from transactions</h2>

<p>HM Land Registry Price Paid Data includes residential sales; it will not capture every option agreement or land deal structured off register, but it stops you inventing numbers. Pair sold evidence with planning application searches via <a href="https://www.planning.data.gov.uk/" rel="noopener noreferrer">planning.data.gov.uk</a> to see whether similar plots gained consent recently.</p>

<h2>What to pay, practically</h2>

<p>Build a residual appraisal: gross development value minus costs, profit, and finance, leaves land. If your land number needs heroic assumptions on sales rate or grant of consent, walk away or reprice. Hope value is fine as upside, not as the base case that secures your loan.</p>

<h2>Morecambe, Lancaster, and the coast</h2>

<p>LA postcodes cover Lancaster district and Morecambe Bay fringe. Coastal flood risk, second home dynamics, and infrastructure projects can shift demand faster than inland towns. Check the local plan allocations for housing and employment land west of Lancaster before you assume a field is future residential. National planning data on <a href="https://www.planning.data.gov.uk/" rel="noopener noreferrer">planning.data.gov.uk</a> helps you see whether similar sites already gained permission nearby.</p>

<h2>Monitoring new land instructions</h2>

<p>Auction houses and agents drop land in bursts. If you care about specific PR, BB, LA, or FY corridors, set up property alerts on Land &amp; Property Northwest so you see new land related lines when we ingest them, without polling five different sites.</p>

<h2>Agricultural ties and overage</h2>

<p>Rural Lancashire parcels sometimes carry agricultural occupancy conditions or overage clauses buried in old transfers. They do not always show clearly on portal blurbs. Your solicitor must read the title and supporting deeds, not just the sales particulars. HM Land Registry title downloads help, but they do not replace full deed chains when conditions were imposed decades ago.</p>

<h2>Utilities and site investigations</h2>

<p>Greenfield land without adoptable services is not a bargain if you must fund a new water main across half a mile of third party verge. Order early utility searches and speak to statutory undertakers before you agree non refundable deposits. On edge of settlement sites near Chorley or Leyland, highways adoption and visibility splays often cost more than the vendor's asking price implied.</p>

<h2>Tax and structure</h2>

<p>Stamp duty land tax, VAT on commercial plots, and capital gains treatment all depend on your structure and the seller's status. Take professional tax advice before you option land through a new SPV. The headline acre price is irrelevant if the post tax return does not clear your hurdle rate.</p>
HTML;
	}

	/**
	 * @return string HTML post body.
	 */
	private static function content_permitted_development(): string {
		return <<<'HTML'
<p>Permitted development rights let you carry out certain types of work and change of use without submitting a full planning application, subject to prior approval processes where they apply. For developers, PD is a timetable and risk tool. For neighbours, it is often controversial. For you, it is a set of rules that change with national direction and local constraints.</p>

<h2>Class MA and high street stock</h2>

<p>Changes between certain commercial, business, and service uses, including routes aimed at reviving high streets, can matter if you hold ground floor units with flats above in places like Chorley, Altrincham, or Southport. Hours of operation, noise, and servicing still create neighbour conflict even when the use class shift is permitted. Read the specific class conditions and talk to environmental health early if you are changing intensity of trade.</p>

<h2>Common PD routes that matter on Northwest stock</h2>

<p>Office to residential, certain agricultural buildings to homes, upward extensions on detached blocks in some settings, and larger domestic extensions all appear in urban fringe and town centre contexts across Manchester, Liverpool, and Lancashire. Each class has limits on size, location, and protected areas. The legislation is the starting point; the local plan and Article 4 directions are the gatekeepers.</p>

<h2>Article 4 in practice</h2>

<p>Councils remove PD in conservation areas, around HMO concentrations, or to protect character in terraced streets. That single sheet on the portal can turn a permitted scheme into a full application overnight. Before you buy on a PD thesis, download the Article 4 map for the relevant authority, whether that is Manchester City Council, Liverpool City Council, or a Lancashire district.</p>

<h2>Prior approval is not a free pass</h2>

<p>Many PD routes require prior approval on issues such as transport, contamination, flooding, and impact on amenity. You still prepare drawings, pay fees, and accept refusal risk. Factor professional fees and delay like a mini planning app, because operationally it behaves like one.</p>

<h2>Neighbours, daylight, and design</h2>

<p>Even where PD is technically available, bad massing produces complaints and later enforcement attention. If you are adding storeys, get daylight and overshadowing checked properly. In dense M and L postcodes, party wall and structural issues often cost more than the facade package.</p>

<h2>Timelines and sales strategy</h2>

<p>Prior approval decisions have statutory periods; councils can agree extensions. If you are buying to refinance or sell on with consent in place, build contingency into your bridge or equity schedule. A six week slip on a PD prior approval can wipe your margin if you are paying interest on land banking finance.</p>

<h2>Track policy and applications together</h2>

<p>Use planning.data.gov.uk to see how authorities are processing PD prior approvals in your target wards. If you want automated nudges when relevant applications hit our database, set up property alerts on Land &amp; Property Northwest for the postcodes you follow.</p>

<h2>Listed buildings and conservation</h2>

<p>PD rights are reduced or removed for listed buildings and can be constrained in conservation areas. Chester city centre, parts of Lancaster, and suburban conservation zones in South Manchester are not places to assume office to resi will fly without full planning. Check the heritage statements on related applications in the same street before you buy a listed shell on a PD hunch.</p>

<h2>Neighbour engagement</h2>

<p>Prior approval consultations can flush out objectors who were silent at purchase. Drop a polite note through doors with a contact number before the council letter lands. It does not guarantee support, but it reduces surprise ambush at committee if the scheme later needs full planning after a PD refusal. Document tree positions, shared drives, and bin storage because those details drive officer reports.</p>
HTML;
	}

	/**
	 * @return string HTML post body.
	 */
	private static function content_market_trends_2026(): string {
		return <<<'HTML'
<p>By 2026 the Northwest market has largely digested the rate shock. Sellers who needed to sell have sold. Buyers who needed certainty on debt costs have rebuilt models around longer holds and tighter refurb margins. What is left is a more two tier market: assets with obvious income or planning upside move; assets with vague stories sit until the price cuts.</p>

<h2>New build sales and incentives</h2>

<p>Housebuilders have adjusted incentives rather than headline list prices in many corridors. Part exchange and stamp contribution packages distort net entry price. Compare like for like using HM Land Registry Price Paid Data on resales nearby, not just show home brochures. In SK and WA commuter zones, new supply can soften second hand stock if transport timing and school catchments do not keep pace with marketing claims.</p>

<h2>Residential buy to let</h2>

<p>Yields in Liverpool, parts of Manchester, and pockets of east Lancashire still print higher than southern comparables, but regulation and maintenance inflation eat into net income. Licensing, EPC floors, and selective enforcement mean underwriting has to be conservative. The edge now is stock selection and operational discipline, not leverage alone.</p>

<h2>Development and conversion</h2>

<p>Smaller schemes, one to twenty units, remain the workable lane for many private developers. Large sites still attract institutional capital, especially where infrastructure is de risked. Change of use and PD led projects continue to appear in planning feeds for town centres from Warrington to Chester, but committee politics on design and affordable provision can slow timelines.</p>

<h2>Industrial and logistics</h2>

<p>Corridors around Warrington (WA), parts of Cheshire (CH, CW), and M62 linked towns still see demand for last mile and mid box space. Not every field is suitable; grid connection, access, and local plan allocation still decide winners. Watch allocation reviews and major planning applications on <a href="https://www.planning.data.gov.uk/" rel="noopener noreferrer">planning.data.gov.uk</a> for early signals.</p>

<h2>Retail and suburban parades</h2>

<p>High street restructuring continues. Investors who understand turnover rents, service charge leakage, and permitted development exit routes can pick up parades others avoid. Conversely, betting on passive retail income without tenant covenants is fragile. In Liverpool and Manchester suburbs, convenience anchored strips still trade, but secondary fashion and comparison retail does not.</p>

<h2>Data led decision making</h2>

<p>HM Land Registry Price Paid Data remains the honest record of what completed. Use it to challenge agent guides and vendor expectations. Layer planning and EPC signals to see where activity is accelerating before headline indices move.</p>

<h2>Staying ahead of the tape</h2>

<p>When the market is selective, timing beats generic optimism. If you want consolidated alerts across Northwest sources, set up property alerts on Land &amp; Property Northwest and react when records match your rules, not when you remember to check portals.</p>

<h2>Workforce mobility and rental demand</h2>

<p>Hybrid working stuck for many firms. That shifts rental demand toward extra bedroom space and reliable broadband in suburbs such as Crosby, Woolton, and Didsbury, while pure city centre micro flats compete harder on incentives. Underwrite rents against local employment data, not against 2019 assumptions. Land Registry repeat sales in the same block tell you whether landlords are actually achieving the asking rents you see on portals.</p>

<h2>Energy costs and operating expenditure</h2>

<p>Service charges on city centre blocks rose with insurance and energy inflation. When you model 2026 holds, stress service charge increases and sinking fund demands, not just mortgage rates. A flat that looks cheap on price per square foot can bleed cash if the management company is playing catch up on fire safety works.</p>

<h2>Political and policy risk</h2>

<p>Rent reform debates and licensing expansions remain live topics. Build a small contingency in your underwriting for compliance costs you cannot yet quantify. The investors who survive policy shifts are the ones who kept leverage modest and maintained cash reserves for works, not the ones who maxed every line.</p>
HTML;
	}

	/**
	 * @return string HTML post body.
	 */
	private static function content_bmv_liverpool(): string {
		return <<<'HTML'
<p>Below market value is not a sticker on an agent window. It is a gap between what you pay and what the asset is worth to you once risk is priced. In Liverpool and wider Merseyside, that gap still appears when sellers prioritise speed, when probate executors need closure, or when stock is tired and finance buyers walk past.</p>

<h2>Knowsley and the eastern fringe</h2>

<p>Knowsley (L32, L33, L36 and adjacent) behaves differently from waterfront postcodes. Employment sites near the M57 and M62 support stable family rental demand in parts, while other wards need granular street vetting. Cross reference Land Registry sold prices with Google Street View dates so you are not comparing a freshly renovated comp against a wreck you are offered.</p>

<h2>Where to look on the map</h2>

<p>L8, L15, and L17 have long been active for investors comfortable with street level due diligence. Bootle (L20, L30) and parts of Sefton offer lower entry with management intensity. Wirral (CH41 to CH49) mixes strong suburbs and harder estates within short distances. Always read crime, flood, and transport data for the specific segment, not the borough average.</p>

<h2>Sources that create BMV</h2>

<p>Auction catalogues from Pugh and Auction House North West often include Merseyside stock where mortgage buyers are thin on the ground. Probate sales sometimes surface on conventional portals but with incomplete information until you speak to the agent. Land Registry completions tell you what actually cleared, which helps you avoid fantasy comparables.</p>

<h2>Do the maths on refurb and licensing</h2>

<p>A cheap house with ten thousand pounds of damp and structural work is not BMV if you paid only five thousand under list. Add voids, selective licensing in Liverpool wards, and HMO standards if you are room letting. Your purchase price should survive a downside case on rent and refinance.</p>

<h2>Off market and networks</h2>

<p>Relationships with local solicitors, small builders, and independent agents still produce deals that never hit Rightmove. That will not scale for everyone, but it complements data led scanning. Be ready to exchange quickly and prove funds; vague buyers do not get called back.</p>

<h2>Finance and survey down valuations</h2>

<p>BMV only exists if your lender agrees with your value or you are cash. Bridge lenders take a conservative view on Merseyside terraces if the comparable set is thin. Keep a cash buffer for renegotiation if the surveyor flags subsidence, Japanese knotweed, or non standard construction. A low purchase price does not fix a failed mortgage retention.</p>

<h2>Let alerts do the first sift</h2>

<p>You cannot refresh every source daily. Set up property alerts on Land &amp; Property Northwest for your Merseyside postcodes and price bands so new auction, planning, and listing derived records hit your inbox when they match what you are trying to buy.</p>

<h2>When BMV is not ethical</h2>

<p>If a seller is in distress, you still need clear advice, written terms, and compliance with consumer protection rules where they apply. Exploitation invites reputational damage and regulatory attention. Professional buyers move fast but leave a paper trail that would survive a courtroom. Your edge should be process and data, not pressure.</p>

<h2>Chains and part exchange</h2>

<p>Sometimes BMV for you is simply certainty for a seller who cannot risk a chain. Taking their problem property as a part exchange line on a larger deal can work if you have disposal routes through auction or trade buyers. Model two exits before you agree: one optimistic, one grim. If only the optimistic exit pays your legal costs, walk away.</p>

<h2>Refurbishment and comparable sales</h2>

<p>BMV disappears if you over improve for the street. Use HM Land Registry Price Paid Data for unextended neighbours, then adjust for your planned specification. If your post refurb value needs buyers from outside the postcode, your resale liquidity may be thinner than you think.</p>
HTML;
	}

	/**
	 * @return string HTML post body.
	 */
	private static function content_section_106(): string {
		return <<<'HTML'
<p>A Section 106 agreement is a legal obligation tied to planning permission. It secures contributions toward infrastructure and community impacts that come from development. In England, it sits alongside the Community Infrastructure Levy where CIL applies. For Northwest schemes, you will see 106 on everything from a small residential infill in Stockport to a major logistics pad in Cheshire.</p>

<h2>What typically appears</h2>

<p>Affordable housing provision or commuted sums, highways works, public transport, education places, public open space, and ecological mitigation all show up depending on scale. The local planning authority negotiates against published viability evidence. What is policy in the local plan is the starting point for negotiation, not the agent's first offer.</p>

<h2>Viability and renegotiation</h2>

<p>If costs move between application and implementation, authorities may consider viability reviews, but they are not automatic handouts. You need transparent appraisals, comparable evidence, and a clear link between the change in circumstances and the request. Courts and inspectors have seen every trick; bluffing wastes time.</p>

<h2>Northwest authority flavour</h2>

<p>Greater Manchester districts and Liverpool City Region authorities vary in how hard they push upfront affordable percentages versus later review mechanisms. Cheshire East and Cheshire West have different settlement patterns and transport priorities. Read the emerging local plan and the infrastructure delivery plan for the council that will actually determine your application, whether that is Trafford, Wirral, Lancaster, or another body.</p>

<h2>Unilateral undertakings and planning obligations</h2>

<p>Sometimes obligations arrive as unilateral undertakings rather than a full bilateral agreement. Your solicitor must compare the drafting against the officer report and decision notice. Indexed sums, stage payments, and triggers linked to occupation or floor space need a spreadsheet, not a handshake. If you acquire land with historic 106 burdens, check whether they were discharged or modified on subsequent applications.</p>

<h2>Monitoring and discharge</h2>

<p>106 obligations run with the land. Your solicitor must check that triggers, timing, and indexing are understood before you complete on a site with extant permission. Failure to comply can block commencement certificates or future disposals. Treat monitoring fees as part of your overhead, not an afterthought.</p>

<h2>CIL and 106 interaction</h2>

<p>Where Community Infrastructure Levy applies, you may pay CIL as well as delivering 106 obligations, unless specific relief or credit arrangements apply. The interaction is authority specific. Cheshire West and Chester, Manchester, and Liverpool each publish charging schedules and guidance notes. Model both lines in your appraisal before you bid land auctions or option agreements.</p>

<h2>Seeing applications early</h2>

<p>Major 106 packages appear in officer reports once applications are public. Tracking at <a href="https://www.planning.data.gov.uk/" rel="noopener noreferrer">planning.data.gov.uk</a> helps you spot large schemes; if you want filtered alerts across Northwest authorities, set up property alerts on Land &amp; Property Northwest for the geographies you care about.</p>

<h2>Delivery risk and phasing</h2>

<p>Large sites often phase affordable delivery alongside market housing. Your solicitor should tie payment triggers to realistic build programmes. If infrastructure contributions assume a road junction upgrade in year three, but sales start in year one, someone must fund the gap. Model those cash flows before you sign a land contract that references the whole 106 suite.</p>

<h2>Legal advice and surveyor input</h2>

<p>This article is practical commentary, not legal advice. Section 106 drafting should always pass through a planning solicitor who knows the authority in question. RICS valuers and viability consultants should sign off numbers you present to councils. If you are buying a site with existing obligations, you inherit the burden unless a deed of variation is negotiated as part of your purchase.</p>

<h2>Template policies and emerging practice</h2>

<p>Many authorities publish template heads of terms for affordable housing and highways. Read them before you open negotiations so you know what is standard for that council versus what is genuinely negotiable. Inspectors on appeals still care about consistency between written policies and what officers accept on the ground.</p>
HTML;
	}

	/**
	 * @return string HTML post body.
	 */
	private static function content_auction_house_guide(): string {
		return <<<'HTML'
<p>If you buy Northwest property at auction, you will almost certainly touch one of a handful of national and regional auctioneers. Each has its own catalogue rhythm, buyer fees, and mix of residential, commercial, and land. Learn the players so you know where your lot type usually appears.</p>

<h2>Buyer fees and admin charges</h2>

<p>Buyer's premium, administration charges, and VAT on fees can add several percentage points to your effective purchase price. SDL and Allsop publish fee structures in catalogue footnotes; Pugh and Auction House North West do the same in their terms. Build the full fee stack into your ceiling bid, not as an afterthought on completion day.</p>

<h2>Calendar discipline</h2>

<p>Major houses often run monthly or fortnightly cycles with catalogues closing on different weekdays. If you chase lots in CH, WA, and M postcodes simultaneously, clashes are common. Diarise legal pack deadlines, pre auction enquiries cut offs, and deposit transfer dates. Missing the enquiry window because you confused SDL's timer with Pugh's room sale is an own goal.</p>

<h2>Pugh</h2>

<p>Pugh is a major name in Northern auctions with regular sales that include residential and commercial stock across the region. Expect clear online catalogues and standard modern method of auction as well as room and livestream formats depending on the sale. Always read the addendum sheet on the morning of the auction; legal packs change.</p>

<h2>SDL Property Auctions</h2>

<p>SDL runs national online auctions with strong coverage of tenanted stock, probate, and lender led disposals. Northwest postcodes appear every month. Their buyer premium and completion terms are stated in the catalogue; model them in your top price before you raise a paddle or click bid.</p>

<h2>Allsop</h2>

<p>Allsop's residential and commercial auctions are widely watched by institutional and private buyers. Competitive bidding on well let blocks can push pricing, but individual lots still slip through when the buyer pool is thin. Worth bookmarking for larger tickets and portfolio pieces touching Manchester and Liverpool city regions.</p>

<h2>Auction House North West</h2>

<p>Auction House North West focuses on the geography its name suggests, with stock from agents and vendors who want certainty of sale. You will see terraces, shops with flats above, and land parcels typical of M, L, PR, BB, CH, WA, and FY postcodes. Local knowledge still wins; a lot in St Helens is not the same risk as a lot in Wilmslow even if guide prices look similar.</p>

<h2>How professionals prepare</h2>

<p>They standardise legal review, use a shortlist of solicitors who turn packs around fast, and never bid without a ceiling tied to gross development value or net yield. They cross check addresses against HM Land Registry data where available and scan planning.data.gov.uk for immediate red flags such as enforcement or recent refusal on the same site.</p>

<h2>Never miss a catalogue drop</h2>

<p>Catalogues overlap and closing times differ. If you want one stream for Northwest focused lots across multiple auction houses, set up property alerts on Land &amp; Property Northwest so new auction lines surface alongside planning and EPC signals you already track.</p>

<h2>Proxy bids and maximum bids</h2>

<p>Online platforms let you leave a maximum bid that escalates in set increments against other bidders. That protects you from heat of the moment overspend only if your maximum was rational before you typed it. Re run your numbers when the legal pack updates. A refreshed addendum that adds a ground rent charge or removes a planning warranty should force a new ceiling, not a shrug.</p>

<h2>Room auctions and telephone bidding</h2>

<p>If you attend in person at a regional room sale, register early and bring photo ID the house requires. Telephone and proxy bidding arrangements differ by auctioneer; read the small print about connection quality and who holds the authority to bid on your behalf. A missed phone handover during a fast lot can cost you the deal or leave you committed at a level you did not intend.</p>
HTML;
	}
}
