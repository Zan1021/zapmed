"""
Zapmed Blog Image Fetcher
--------------------------
Fetches featured images from zapmed.co.za WordPress blog posts
via the WP REST API, converts them to WebP, saves them locally,
and updates the SQLite database with the image paths.

Usage:
    python fetch_blog_images.py

Requirements:
    pip install requests Pillow
"""

import os
import sys
import json
import sqlite3
import requests
from pathlib import Path
from io import BytesIO

try:
    from PIL import Image
except ImportError:
    print("ERROR: Pillow is required. Install with: pip install Pillow")
    sys.exit(1)

# Config
WP_API_URL = "https://zapmed.co.za/wp-json/wp/v2/posts"
PROJECT_ROOT = Path(__file__).parent
STORAGE_PATH = PROJECT_ROOT / "storage" / "app" / "public" / "blog"
DB_PATH = PROJECT_ROOT / "database" / "database.sqlite"
MAX_WIDTH = 1200  # Resize images to max 1200px wide
WEBP_QUALITY = 82

def fetch_all_posts():
    """Fetch all published posts with embedded featured media from WP API."""
    all_posts = []
    page = 1
    per_page = 50

    while True:
        print(f"  Fetching page {page}...")
        resp = requests.get(WP_API_URL, params={
            'per_page': per_page,
            'page': page,
            '_embed': True,
            'status': 'publish'
        }, timeout=30)

        if resp.status_code == 400:
            break  # No more pages

        resp.raise_for_status()
        posts = resp.json()

        if not posts:
            break

        all_posts.extend(posts)
        page += 1

        # Check if there are more pages
        total_pages = int(resp.headers.get('X-WP-TotalPages', 1))
        if page > total_pages:
            break

    return all_posts


def get_featured_image_url(post):
    """Extract the best featured image URL from embedded post data."""
    # Method 1: Try _embedded data
    try:
        media = post['_embedded']['wp:featuredmedia'][0]
        sizes = media.get('media_details', {}).get('sizes', {})
        if 'large' in sizes:
            return sizes['large']['source_url']
        elif 'full' in sizes:
            return sizes['full']['source_url']
        else:
            return media.get('source_url')
    except (KeyError, IndexError, TypeError):
        pass

    # Method 2: Fetch media directly by featured_media ID
    media_id = post.get('featured_media')
    if media_id and media_id > 0:
        try:
            resp = requests.get(
                f"https://zapmed.co.za/wp-json/wp/v2/media/{media_id}",
                timeout=15
            )
            if resp.status_code == 200:
                media = resp.json()
                sizes = media.get('media_details', {}).get('sizes', {})
                if 'large' in sizes:
                    return sizes['large']['source_url']
                return media.get('source_url')
        except Exception:
            pass

    return None


def download_and_convert_image(url, slug):
    """Download image from URL, convert to WebP, save locally."""
    try:
        resp = requests.get(url, timeout=30, headers={
            'User-Agent': 'ZapmedBlogImporter/1.0'
        })
        resp.raise_for_status()

        img = Image.open(BytesIO(resp.content))

        # Convert to RGB if necessary (e.g., PNG with alpha)
        if img.mode in ('RGBA', 'P'):
            img = img.convert('RGB')

        # Resize if wider than MAX_WIDTH
        if img.width > MAX_WIDTH:
            ratio = MAX_WIDTH / img.width
            new_height = int(img.height * ratio)
            img = img.resize((MAX_WIDTH, new_height), Image.LANCZOS)

        # Save as WebP
        filename = f"{slug}.webp"
        filepath = STORAGE_PATH / filename
        img.save(filepath, 'WEBP', quality=WEBP_QUALITY)

        return f"blog/{filename}"

    except Exception as e:
        print(f"    ERROR downloading {url}: {e}")
        return None


def update_database(slug_to_image):
    """Update blog_posts table with featured_image paths."""
    if not DB_PATH.exists():
        print(f"  WARNING: Database not found at {DB_PATH}")
        print("  Images downloaded but database NOT updated.")
        print("  Run 'php artisan' to update manually, or check DB path.")
        return 0

    conn = sqlite3.connect(str(DB_PATH))
    cursor = conn.cursor()

    updated = 0
    for slug, image_path in slug_to_image.items():
        cursor.execute(
            "UPDATE blog_posts SET featured_image = ? WHERE slug = ? AND (featured_image IS NULL OR featured_image = '')",
            (image_path, slug)
        )
        if cursor.rowcount > 0:
            updated += 1

    conn.commit()
    conn.close()
    return updated


def main():
    print("=" * 60)
    print("  ZAPMED BLOG IMAGE FETCHER")
    print("=" * 60)
    print()

    # Create storage directory
    STORAGE_PATH.mkdir(parents=True, exist_ok=True)
    print(f"[1/4] Storage directory: {STORAGE_PATH}")

    # Fetch posts from WP API
    print(f"\n[2/4] Fetching posts from {WP_API_URL}...")
    posts = fetch_all_posts()
    print(f"  Found {len(posts)} published posts")

    # Download and convert images
    print(f"\n[3/4] Downloading and converting featured images to WebP...")
    slug_to_image = {}
    downloaded = 0
    skipped = 0
    failed = 0

    for post in posts:
        slug = post['slug']
        title = post['title']['rendered'][:50]

        # Check if already downloaded
        webp_path = STORAGE_PATH / f"{slug}.webp"
        if webp_path.exists():
            slug_to_image[slug] = f"blog/{slug}.webp"
            skipped += 1
            print(f"  SKIP (exists): {slug}")
            continue

        image_url = get_featured_image_url(post)
        if not image_url:
            print(f"  SKIP (no image): {slug}")
            skipped += 1
            continue

        print(f"  Downloading: {slug}...")
        result = download_and_convert_image(image_url, slug)

        if result:
            slug_to_image[slug] = result
            downloaded += 1
        else:
            failed += 1

    print(f"\n  Results: {downloaded} downloaded, {skipped} skipped, {failed} failed")

    # Update database
    print(f"\n[4/4] Updating database...")
    if slug_to_image:
        updated = update_database(slug_to_image)
        print(f"  Updated {updated} blog post records in database")
    else:
        print("  No images to update")

    # Summary
    print("\n" + "=" * 60)
    print("  DONE!")
    print(f"  Images saved to: {STORAGE_PATH}")
    print(f"  Total posts processed: {len(posts)}")
    print(f"  Images downloaded: {downloaded}")
    if slug_to_image:
        print(f"\n  Don't forget to run: php artisan storage:link")
        print(f"  (if not already linked)")
    print("=" * 60)


if __name__ == "__main__":
    main()
