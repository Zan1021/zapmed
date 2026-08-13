"""
Fetch treatment page content from zapmed.co.za WordPress site
and update local blade content files + download hero images.

Strategy: Always scrape the rendered HTML page because the WP REST API
returns raw Divi shortcode markup that is not usable as content.
"""
import os
import re
import time
import requests
from pathlib import Path
from io import BytesIO

from bs4 import BeautifulSoup, NavigableString
from PIL import Image

PROJECT_ROOT = Path(__file__).parent
CONTENT_DIR = PROJECT_ROOT / "resources" / "views" / "treatments" / "content"
IMAGES_DIR = PROJECT_ROOT / "public" / "images" / "treatments"

BASE_URL = "https://zapmed.co.za"
WP_API_URL = f"{BASE_URL}/wp-json/wp/v2/pages"

# Live site slug => local file slug
SLUG_MAP = {
    "acne-treatment": "acne",
    "anti-aging-treatment": "anti-ageing",
    "rosacea-treatment": "rosacea-treatment",
    "bacterial-vaginosis-treatment": "bacterial-vaginosis-treatment",
    "birth-control": "birth-control",
    "menopause-management": "menopause-management",
    "erectile-dysfunction-treatment": "erectile-dysfunction",
    "premature-ejaculation-treatment": "premature-ejaculation",
    "genital-herpes-101": "genital-herpes",
    "genital-warts-treatment": "genital-warts-treatment",
    "sti-treatment": "sti-treatment",
    "acid-reflux-treatment": "acid-reflux-treatment",
    "cold-sores-treatment": "cold-sores",
    "gp-consult": "gp-consult",
    "haemorrhoids-treatment": "haemorrhoids-treatment",
    "hair-loss-treatment": "hair-loss",
    "uti-treatment": "uti-treatment",
    "thrush-treatment": "thrush-treatment",
    "health-coach-support": "health-coach-support",
}

# Request headers
HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
}

MIN_CONTENT_LENGTH = 100  # Don't overwrite files with more than this many chars


def fetch_page_html(slug):
    """Fetch the rendered HTML page from the live site."""
    url = f"{BASE_URL}/{slug}/"
    try:
        resp = requests.get(url, headers=HEADERS, timeout=30)
        if resp.status_code == 200:
            return resp.text
        else:
            print(f"    HTTP {resp.status_code} for {url}")
    except Exception as e:
        print(f"    Fetch error for {slug}: {e}")
    return None


def fetch_featured_image_via_api(slug):
    """Use the REST API only to get the featured image URL."""
    try:
        resp = requests.get(
            WP_API_URL,
            params={"slug": slug, "_embed": ""},
            headers=HEADERS,
            timeout=30,
        )
        if resp.status_code == 200:
            pages = resp.json()
            if pages and len(pages) > 0:
                embedded = pages[0].get("_embedded", {})
                featured_media = embedded.get("wp:featuredmedia", [])
                if featured_media and len(featured_media) > 0:
                    return featured_media[0].get("source_url")
    except Exception:
        pass
    return None


def extract_content_from_rendered_html(html_text):
    """
    Extract main body content and FAQ from rendered Divi page HTML.
    The rendered page has actual HTML (no shortcodes) in .et_pb_text_inner divs.
    """
    soup = BeautifulSoup(html_text, "html.parser")

    # Remove script/style/nav/footer/header
    for tag in soup.find_all(["script", "style", "nav", "noscript"]):
        tag.decompose()

    # Find the main page content area
    page_container = soup.find("div", {"id": "page-container"})
    if not page_container:
        page_container = soup

    # Remove header and footer
    header = page_container.find("header")
    if header:
        header.decompose()
    footer = page_container.find("footer")
    if footer:
        footer.decompose()

    # Collect meaningful text sections
    body_parts = []
    faq_parts = []

    # Strategy 1: Extract from .et_pb_text_inner divs (Divi text modules)
    text_modules = page_container.find_all("div", class_="et_pb_text_inner")

    for module in text_modules:
        content_html = get_clean_inner_html(module)
        text_content = module.get_text(strip=True)

        if not text_content or len(text_content) < 20:
            continue

        # Skip CTA/pricing/navigation blocks
        if is_cta_or_pricing_block(text_content):
            continue

        # Check if this is a section heading only (like "Our Offering", "How it works")
        if is_section_heading_only(module):
            continue

        body_parts.append(content_html)

    # Strategy 2: Extract FAQ from accordion/toggle modules
    faq_parts = extract_faq_from_page(page_container)

    # Combine body parts into clean prose HTML
    body_html = clean_and_combine_body(body_parts)
    faq_html = format_faq_html(faq_parts)

    return body_html, faq_html


def is_cta_or_pricing_block(text):
    """Check if text block is a CTA, pricing, or navigation element."""
    lower = text.lower()
    cta_patterns = [
        "get treatment",
        "start your consultation",
        "book now",
        "subscribe now",
        "add to cart",
        "sign up",
        "how it works",
        "our offering",
        "subscription service",
        "once-off",
        "per month",
        "/pm",
        "r220",
        "r320",
        "r340",
        "r450",
        "r570",
        "medical aid from",
        "cash from",
        "prescription fees",
        "doctor fees",
        "medication delivery",
        "chat to us live",
        "got any questions",
    ]
    # Short text that matches CTA patterns
    if len(text) < 150 and any(p in lower for p in cta_patterns):
        return True
    # Pricing cards
    if re.search(r"r\d+.*?/pm|from\s+r\d+", lower):
        return True
    return False


def is_section_heading_only(element):
    """Check if element only contains a heading with no real body text."""
    children = list(element.children)
    text_content = element.get_text(strip=True)
    headings = element.find_all(["h1", "h2", "h3", "h4", "h5", "h6"])

    if headings and len(text_content) < 80:
        # Only heading text, no body content
        heading_text = " ".join(h.get_text(strip=True) for h in headings)
        if len(heading_text) >= len(text_content) * 0.8:
            return True
    return False


def get_clean_inner_html(element):
    """Get clean HTML from a BeautifulSoup element, preserving structure."""
    # Clone the element to avoid modifying the original
    from copy import copy
    el = BeautifulSoup(str(element), "html.parser")

    # Remove all attributes except href, src, alt
    for tag in el.find_all(True):
        allowed_attrs = {}
        if tag.name == "a" and tag.get("href"):
            href = tag["href"]
            # Don't include internal app links or CTA links
            if "product-application-form" in href or "app.contro" in href or "app.zapmed" in href:
                # Replace CTA links with just the text
                tag.replace_with(tag.get_text())
                continue
            if "zapmed.co.za" in href:
                href = href.replace("https://zapmed.co.za", "").replace("http://zapmed.co.za", "")
            allowed_attrs["href"] = href
        if tag.name == "img":
            if tag.get("src"):
                allowed_attrs["src"] = tag["src"]
            if tag.get("alt"):
                allowed_attrs["alt"] = tag["alt"]
        tag.attrs = allowed_attrs

    # Remove empty elements (except br, hr, img)
    for tag in el.find_all(True):
        if tag.name in ("br", "hr", "img"):
            continue
        if not tag.get_text(strip=True) and not tag.find("img"):
            tag.decompose()

    html_str = el.decode_contents().strip()

    # Remove the outer wrapper div if present
    html_str = re.sub(r"^<div>\s*", "", html_str)
    html_str = re.sub(r"\s*</div>$", "", html_str)

    # Clean whitespace
    html_str = re.sub(r"\n{3,}", "\n\n", html_str)
    html_str = re.sub(r"[ \t]+\n", "\n", html_str)

    return html_str.strip()


def extract_faq_from_page(container):
    """Extract FAQ items from Divi accordion/toggle modules."""
    faq_items = []

    # Divi accordion items
    accordion_items = container.find_all("div", class_="et_pb_toggle")
    if not accordion_items:
        accordion_items = container.find_all("div", class_="et_pb_accordion_item")

    for item in accordion_items:
        # Get question from title
        title_el = (
            item.find(class_="et_pb_toggle_title")
            or item.find("h5", class_="et_pb_toggle_title")
            or item.find("h2", class_="et_pb_toggle_title")
        )
        # Get answer from content
        content_el = item.find("div", class_="et_pb_toggle_content")

        if title_el and content_el:
            question = title_el.get_text(strip=True)
            answer = get_clean_inner_html(content_el)
            if question and answer and len(answer) > 20:
                faq_items.append({"q": question, "a": answer})

    return faq_items


def clean_and_combine_body(parts):
    """Combine body parts into clean prose HTML."""
    if not parts:
        return ""

    # Filter out very short parts that are likely headings/labels
    meaningful_parts = []
    for part in parts:
        text_only = re.sub(r"<[^>]+>", "", part).strip()
        if len(text_only) > 30:
            meaningful_parts.append(part)

    if not meaningful_parts:
        return ""

    combined = "\n\n".join(meaningful_parts)

    # Remove any remaining Divi shortcodes that slipped through
    combined = re.sub(r"\[/?et_pb_[^\]]*\]", "", combined)

    # Clean up empty paragraphs
    combined = re.sub(r"<p>\s*</p>", "", combined)

    # Remove multiple consecutive newlines
    combined = re.sub(r"\n{3,}", "\n\n", combined)

    return combined.strip()


def format_faq_html(faq_items):
    """Format FAQ items as clean HTML for a blade partial."""
    if not faq_items:
        return ""

    parts = []
    for item in faq_items:
        q = item["q"]
        a = item["a"]
        # Remove any Divi shortcodes
        a = re.sub(r"\[/?et_pb_[^\]]*\]", "", a)
        parts.append(f"<h3>{q}</h3>\n{a}")

    return "\n\n".join(parts)


def extract_hero_image_from_html(html_text):
    """Find the hero/banner image from the rendered page HTML."""
    soup = BeautifulSoup(html_text, "html.parser")

    # Strategy 1: Look for Divi fullwidth header background image
    fullwidth_headers = soup.find_all("div", class_=re.compile(r"et_pb_fullwidth_header"))
    for header in fullwidth_headers:
        style = header.get("style", "")
        bg_match = re.search(r"background-image:\s*url\(['\"]?(.*?)['\"]?\)", style)
        if bg_match:
            return bg_match.group(1)

    # Strategy 2: Look for first section with background image
    sections = soup.find_all("div", class_=re.compile(r"et_pb_section"))
    for section in sections[:3]:  # Only check first few sections
        # Check inline style
        style = section.get("style", "")
        bg_match = re.search(r"background-image:\s*url\(['\"]?(.*?)['\"]?\)", style)
        if bg_match:
            url = bg_match.group(1)
            if "logo" not in url.lower() and "icon" not in url.lower():
                return url

    # Strategy 3: First large image in the top portion of the page
    page_container = soup.find("div", {"id": "page-container"})
    if page_container:
        # Look in the first few sections
        first_sections = page_container.find_all("div", class_=re.compile(r"et_pb_section"), limit=3)
        for section in first_sections:
            images = section.find_all("img")
            for img in images:
                src = img.get("data-src", "") or img.get("data-lazy-src", "") or img.get("src", "")
                if not src:
                    continue
                # Skip icons, logos, data URIs, SVGs
                if "logo" in src.lower() or "icon" in src.lower() or ".svg" in src.lower():
                    continue
                if src.startswith("data:"):
                    continue
                # Check for reasonable size
                width = img.get("width", "")
                if width:
                    try:
                        if int(width) < 200:
                            continue
                    except ValueError:
                        pass
                if "wp-content/uploads" in src:
                    return src

    # Strategy 4: Any img with treatment-related content
    all_images = soup.find_all("img")
    for img in all_images:
        src = img.get("data-src", "") or img.get("data-lazy-src", "") or img.get("src", "")
        if not src or src.startswith("data:"):
            continue
        if "logo" in src.lower() or "icon" in src.lower() or ".svg" in src.lower():
            continue
        if "wp-content/uploads" in src and ("treatment" in src.lower() or "Treatment" in src):
            return src

    return None


def download_and_convert_image(image_url, local_slug):
    """Download an image, convert to WebP, and save."""
    if not image_url:
        return False

    # Make absolute URL
    if image_url.startswith("//"):
        image_url = "https:" + image_url
    elif image_url.startswith("/"):
        image_url = BASE_URL + image_url

    try:
        resp = requests.get(image_url, headers=HEADERS, timeout=30)
        resp.raise_for_status()

        img = Image.open(BytesIO(resp.content))
        # Convert to RGB if necessary (for WebP)
        if img.mode in ("RGBA", "P", "LA"):
            img = img.convert("RGB")

        # Resize if very large (max 1200px wide)
        if img.width > 1200:
            ratio = 1200 / img.width
            new_size = (1200, int(img.height * ratio))
            img = img.resize(new_size, Image.LANCZOS)

        # Save as WebP
        IMAGES_DIR.mkdir(parents=True, exist_ok=True)
        output_path = IMAGES_DIR / f"{local_slug}.webp"
        img.save(str(output_path), "WEBP", quality=80)
        print(f"    IMAGE SAVED: {output_path.relative_to(PROJECT_ROOT)}")
        return True
    except Exception as e:
        print(f"    Image download error: {e}")
        return False


def extract_intro_from_body(body_html):
    """Extract first 1-2 sentences as intro from body content."""
    if not body_html:
        return ""
    soup = BeautifulSoup(body_html, "html.parser")
    # Find first paragraph with meaningful text
    for p in soup.find_all("p"):
        text = p.get_text(strip=True)
        if len(text) > 40:
            # Take first 1-2 sentences
            sentences = re.split(r"(?<=[.!?])\s+", text)
            if len(sentences) >= 2:
                intro = " ".join(sentences[:2])
            else:
                intro = text
            # Limit to ~250 chars
            if len(intro) > 250:
                intro = intro[:247] + "..."
            return f"<p>{intro}</p>"
    return ""


def file_has_substantial_content(filepath):
    """Check if a file already has substantial content (>100 chars of text)."""
    if not filepath.exists():
        return False
    content = filepath.read_text(encoding="utf-8").strip()
    # Strip HTML tags to check actual text content
    text_only = re.sub(r"<[^>]+>", "", content).strip()
    return len(text_only) > MIN_CONTENT_LENGTH


def write_content_file(filepath, content, file_type="body"):
    """Write content to a blade file if it doesn't already have substantial content."""
    if file_has_substantial_content(filepath):
        print(f"    SKIP (has content): {filepath.name}")
        return False

    if not content or len(content.strip()) < 50:
        print(f"    SKIP (extracted too short): {filepath.name}")
        return False

    filepath.parent.mkdir(parents=True, exist_ok=True)
    filepath.write_text(content.strip() + "\n", encoding="utf-8")
    print(f"    WROTE: {filepath.name} ({len(content)} chars)")
    return True


def get_existing_local_slugs():
    """Get list of local slug names that have content files."""
    slugs = set()
    if not CONTENT_DIR.exists():
        return slugs
    for f in CONTENT_DIR.iterdir():
        if f.name.endswith(".blade.php"):
            # Get the slug part (remove .blade.php suffix, and -faq/-intro suffix)
            name = f.name.replace(".blade.php", "")
            # Strip -faq or -intro suffix to get base slug
            base = re.sub(r"-(faq|intro)$", "", name)
            slugs.add(base)
    return slugs


def main():
    print("=" * 60)
    print("  ZAPMED TREATMENT CONTENT FETCHER")
    print("=" * 60)

    # Get existing local slugs
    local_slugs = get_existing_local_slugs()
    print(f"\nFound {len(local_slugs)} local treatment slugs")
    print(f"Content directory: {CONTENT_DIR}")
    print(f"Images directory: {IMAGES_DIR}")

    total_written = 0
    total_images = 0
    total_skipped = 0

    for live_slug, local_slug in SLUG_MAP.items():
        print(f"\n{'-' * 50}")
        print(f"  Processing: {live_slug} => {local_slug}")
        print(f"{'-' * 50}")

        # Check if local slug exists in our content dir
        if local_slug not in local_slugs:
            print(f"    SKIP: No local blade files for '{local_slug}'")
            total_skipped += 1
            continue

        # Paths for the three content files
        body_file = CONTENT_DIR / f"{local_slug}.blade.php"
        intro_file = CONTENT_DIR / f"{local_slug}-intro.blade.php"
        faq_file = CONTENT_DIR / f"{local_slug}-faq.blade.php"

        # Scrape the rendered page HTML
        print(f"    Fetching rendered page...")
        page_html = fetch_page_html(live_slug)

        if not page_html:
            print(f"    FAILED: Could not fetch page for {live_slug}")
            total_skipped += 1
            continue

        print(f"    Page fetched ({len(page_html)} chars)")

        # Extract content
        body_html, faq_html = extract_content_from_rendered_html(page_html)

        # Write body content
        if body_html and len(body_html) > 100:
            if write_content_file(body_file, body_html, "body"):
                total_written += 1

            # Extract and write intro
            intro_html = extract_intro_from_body(body_html)
            if intro_html:
                write_content_file(intro_file, intro_html, "intro")
        else:
            print(f"    No substantial body content extracted ({len(body_html) if body_html else 0} chars)")

        # Write FAQ content
        if faq_html and len(faq_html) > 100:
            write_content_file(faq_file, faq_html, "faq")

        # Download hero image
        hero_image_url = extract_hero_image_from_html(page_html)
        if not hero_image_url:
            # Fallback: try REST API for featured image
            hero_image_url = fetch_featured_image_via_api(live_slug)

        if hero_image_url:
            print(f"    Found image: {hero_image_url[:80]}...")
            if download_and_convert_image(hero_image_url, local_slug):
                total_images += 1
        else:
            print(f"    No hero image found")

        # Be polite to the server
        time.sleep(1.5)

    print(f"\n{'=' * 60}")
    print(f"  DONE!")
    print(f"  Content files written: {total_written}")
    print(f"  Images downloaded: {total_images}")
    print(f"  Skipped: {total_skipped}")
    print(f"{'=' * 60}")


if __name__ == "__main__":
    main()
