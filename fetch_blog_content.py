"""
Fetch full blog post content from zapmed.co.za WordPress API
and update the local SQLite database body field.
"""
import sqlite3
import requests
import html
import re
from pathlib import Path

PROJECT_ROOT = Path(__file__).parent
DB_PATH = PROJECT_ROOT / "database" / "database.sqlite"
WP_API_URL = "https://zapmed.co.za/wp-json/wp/v2/posts"

# Manual slug mapping: local DB slug => WP slug
SLUG_MAP = {
    "birth-control-101-finding-the-right-contraceptive-for-you-in-south-africa": "birth-control-101-finding-the-right-contraceptive",
    "can-your-relaxing-bubble-bath-secretly-cause-thrush": "can-bubble-baths-cause-thrush-2",
    "genital-warts-treatment-what-to-expect-what-it-costs": "genital-warts-treatment-in-south-africa",
    "payment-troubleshooting-failed-cards-debit-orders-and-refunds": "zapmed-payment-troubleshooting",
    "ex-contro-member-heres-how-to-reactivate-on-zapmed": "reactivate-zapmed-account",
    "how-to-access-your-zapmed-invoice-to-claim-from-your-medical-aid": "zapmed-invoice-medical-aid",
    "where-are-my-meds-a-simple-guide-to-zapmed-delivery-tracking": "zapmed-delivery-tracking",
    "who-is-discreet-online-sti-treatment-in-south-africa-for": "online-sti-treatment-in-sa",
    "3-life-stages-when-a-sexual-health-screening-for-couples-is-most-important": "couples-sexual-health-screening",
    "4-common-myths-about-hair-loss-treatment-in-south-africa": "hair-loss-treatment-myths",
    "four-key-differences-between-wegovy-and-mounjaro-for-medical-weight-loss-in-south-africa": "wegovy-vs-mounjaro",
    "5-things-to-expect-when-starting-erectile-dysfunction-treatment-in-south-africa": "erectile-dysfunction-treatment-in-sa",
    "how-to-prepare-for-your-first-online-doctor-consultation-in-south-africa": "online-doctor-consultation",
    "winter-skin-woes-how-to-keep-your-skin-hydrated-healthy-in-cold-weather": "how-to-keep-your-skin-hydrated-and-healthy",
    "breast-cancer-awareness-month-the-power-of-early-detection-and-self-examination": "breast-cancer-the-power-of-early-detection",
    "zapmeds-comprehensive-approach-to-acne-treatment": "unlocking-clear-skin-contros-approach-to-acne",
    "trichomoniasis-everything-you-need-to-know": "trichomoniasis-101",
    "breaking-down-the-outrageous-pink-tax-unveiling-gender-based-pricing-disparities": "breaking-down-pink-tax",
    "celebrating-womens-health-with-zapmed-this-womens-day": "celebrating-womens-health-with-contro-this-womens-day",
    "your-august-health-hack-6-tips-for-better-living": "your-august-health-hack-tips-for-better-living",
    "protein-vs-amino-acids-whats-the-difference-and-do-you-need-both": "protein-vs-amino-acids",
    "the-starters-guide-to-contraceptive-pills-in-south-africa": "guide-to-contraceptive-pills-in-south-africa",
    "how-much-does-mounjaro-cost-in-south-africa-a-transparent-glp-1-price-comparison": "how-much-does-mounjaro-cost-in-south-africa",
    "why-your-gut-health-matters-how-it-affects-your-skin-mood-more": "why-your-gut-health-matters",
    "understanding-your-skin-type-why-it-matters-and-how-to-identify-yours": "understanding-your-skin-type",
}


def fetch_all_wp_posts():
    """Fetch all posts from WP API."""
    all_posts = []
    page = 1
    while True:
        print(f"  Fetching page {page}...")
        resp = requests.get(WP_API_URL, params={
            'per_page': 50,
            'page': page,
            'status': 'publish'
        }, timeout=30)
        if resp.status_code == 400:
            break
        resp.raise_for_status()
        posts = resp.json()
        if not posts:
            break
        all_posts.extend(posts)
        total_pages = int(resp.headers.get('X-WP-TotalPages', 1))
        if page >= total_pages:
            break
        page += 1
    return all_posts


def clean_content(html_content):
    """Clean up WP content for display - remove empty spacers, fix links."""
    if not html_content:
        return ""
    # Remove WP spacer blocks
    content = re.sub(r'<div[^>]*class="wp-block-spacer"[^>]*>.*?</div>', '', content if 'content' in dir() else html_content)
    content = html_content
    # Remove empty paragraphs
    content = re.sub(r'<p[^>]*>\s*</p>', '', content)
    # Remove rank-math TOC blocks
    content = re.sub(r'<div class="wp-block-rank-math-toc-block".*?</div>\s*</nav>\s*</div>', '', content, flags=re.DOTALL)
    return content.strip()


def main():
    print("=" * 60)
    print("  ZAPMED BLOG CONTENT FETCHER")
    print("=" * 60)

    # Fetch WP posts
    print("\n[1/3] Fetching posts from WordPress API...")
    wp_posts = fetch_all_wp_posts()
    print(f"  Found {len(wp_posts)} posts")

    # Build WP slug -> content map
    wp_content = {}
    for post in wp_posts:
        slug = post['slug']
        content = post.get('content', {}).get('rendered', '')
        if content:
            wp_content[slug] = clean_content(content)

    # Get DB posts
    print("\n[2/3] Reading local database...")
    conn = sqlite3.connect(str(DB_PATH))
    c = conn.cursor()
    c.execute("SELECT id, slug, body FROM blog_posts")
    db_posts = c.fetchall()
    print(f"  Found {len(db_posts)} posts in DB")

    # Update content
    print("\n[3/3] Updating post content...")
    updated = 0
    for post_id, db_slug, current_body in db_posts:
        # Try direct match first
        wp_slug = db_slug
        if db_slug not in wp_content:
            # Try mapped slug
            wp_slug = SLUG_MAP.get(db_slug)

        if wp_slug and wp_slug in wp_content:
            new_content = wp_content[wp_slug]
            if new_content and len(new_content) > 100:
                c.execute("UPDATE blog_posts SET body = ? WHERE id = ?", (new_content, post_id))
                updated += 1
                print(f"  UPDATED: {db_slug} ({len(new_content)} chars)")
            else:
                print(f"  SKIP (short content): {db_slug}")
        else:
            print(f"  MISS: {db_slug}")

    conn.commit()
    conn.close()

    print(f"\n{'=' * 60}")
    print(f"  DONE! Updated {updated}/{len(db_posts)} posts with full content")
    print(f"{'=' * 60}")


if __name__ == "__main__":
    main()
