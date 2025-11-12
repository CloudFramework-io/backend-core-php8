#!/usr/bin/env python3
"""
Script to convert all Markdown API reference files to HTML
"""
import os
import re
from pathlib import Path

# Base paths
DOCS_DIR = Path(__file__).parent
MD_DIR = DOCS_DIR / 'api-reference'
HTML_DIR = DOCS_DIR / 'html' / 'api-reference'

# Sidebar navigation
SIDEBAR_NAV = '''            <nav>
                <a href="../index.html">Home</a>
                <h3>Core Classes</h3>
                <a href="Core7.html">Core7</a>
                <a href="RESTful.html">RESTful</a>
                <a href="Scripts2020.html">Scripts2020</a>
                <h3>Configuration & Security</h3>
                <a href="CoreConfig.html">CoreConfig</a>
                <a href="CoreCache.html">CoreCache</a>
                <a href="CoreSession.html">CoreSession</a>
                <a href="CoreSecurity.html">CoreSecurity</a>
                <h3>Data Storage</h3>
                <a href="DataStore.html">DataStore</a>
                <a href="Buckets.html">Buckets</a>
                <a href="DataBQ.html">DataBQ</a>
                <a href="CloudSQL.html">CloudSQL</a>
                <a href="DataSQL.html">DataSQL</a>
                <a href="DataMongoDB.html">DataMongoDB</a>
                <h3>Utilities</h3>
                <a href="Email.html">Email</a>
                <a href="DataValidation.html">DataValidation</a>
                <a href="WorkFlows.html">WorkFlows</a>
                <h3>GCP Integration</h3>
                <a href="GoogleSecrets.html">GoogleSecrets</a>
                <a href="PubSub.html">PubSub</a>
            </nav>'''

def markdown_to_html(md_content):
    """Convert markdown to HTML"""
    html = md_content

    # Code blocks
    html = re.sub(r'```(\w+)?\n(.*?)```', r'<pre><code class="language-\1">\2</code></pre>', html, flags=re.DOTALL)

    # Headers
    html = re.sub(r'^# (.*?)$', r'<h1>\1</h1>', html, flags=re.MULTILINE)
    html = re.sub(r'^## (.*?)$', r'<h2>\1</h2>', html, flags=re.MULTILINE)
    html = re.sub(r'^### (.*?)$', r'<h3>\1</h3>', html, flags=re.MULTILINE)
    html = re.sub(r'^#### (.*?)$', r'<h4>\1</h4>', html, flags=re.MULTILINE)

    # Bold
    html = re.sub(r'\*\*(.*?)\*\*', r'<strong>\1</strong>', html)

    # Inline code
    html = re.sub(r'`([^`]+)`', r'<code>\1</code>', html)

    # Links - convert .md to .html
    html = re.sub(r'\[([^\]]+)\]\(([^)]+\.md)\)', lambda m: f'<a href="{m.group(2).replace(".md", ".html")}">{m.group(1)}</a>', html)
    html = re.sub(r'\[([^\]]+)\]\(([^)]+)\)', r'<a href="\2">\1</a>', html)

    # Unordered lists
    html = re.sub(r'^\- (.*?)$', r'<li>\1</li>', html, flags=re.MULTILINE)
    html = re.sub(r'(<li>.*?</li>\n)+', r'<ul>\n\g<0></ul>\n', html, flags=re.MULTILINE)

    # Tables
    def convert_table(match):
        lines = match.group(0).split('\n')
        if len(lines) < 3:
            return match.group(0)

        # Header
        headers = [cell.strip() for cell in lines[0].split('|')[1:-1]]

        # Rows (skip separator line)
        rows = []
        for line in lines[2:]:
            if '|' in line:
                cells = [cell.strip() for cell in line.split('|')[1:-1]]
                rows.append(cells)

        # Build HTML table
        table_html = '<table>\n<thead>\n<tr>\n'
        for header in headers:
            table_html += f'<th>{header}</th>\n'
        table_html += '</tr>\n</thead>\n<tbody>\n'

        for row in rows:
            table_html += '<tr>\n'
            for cell in row:
                table_html += f'<td>{cell}</td>\n'
            table_html += '</tr>\n'

        table_html += '</tbody>\n</table>\n'
        return table_html

    html = re.sub(r'\|.*?\|(\n\|.*?\|)+', convert_table, html, flags=re.MULTILINE)

    # Paragraphs (lines that don't start with tags)
    lines = html.split('\n')
    in_block = False
    result = []
    paragraph = []

    for line in lines:
        stripped = line.strip()
        if not stripped:
            if paragraph:
                result.append('<p>' + ' '.join(paragraph) + '</p>')
                paragraph = []
            result.append('')
            continue

        # Check if line starts with HTML tag
        if re.match(r'^\s*<', stripped):
            if paragraph:
                result.append('<p>' + ' '.join(paragraph) + '</p>')
                paragraph = []
            result.append(line)
        else:
            paragraph.append(stripped)

    if paragraph:
        result.append('<p>' + ' '.join(paragraph) + '</p>')

    html = '\n'.join(result)

    # Clean up excessive blank lines
    html = re.sub(r'\n{3,}', '\n\n', html)

    return html

def generate_html_page(title, content, active_nav):
    """Generate complete HTML page"""

    # Update active nav link
    sidebar = SIDEBAR_NAV.replace(f'<a href="{active_nav}.html">', f'<a href="{active_nav}.html" class="active">')

    html = f'''<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{title} - CloudFramework Backend Core PHP8</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="logo">
                <h2>CloudFramework</h2>
                <p>Backend Core PHP8</p>
            </div>
{sidebar}
        </aside>
        <main class="content">
{content}
        </main>
    </div>
    <script src="../js/main.js"></script>
</body>
</html>'''

    return html

def process_file(md_file):
    """Process a single markdown file"""
    print(f"Processing {md_file.name}...")

    # Read markdown
    with open(md_file, 'r', encoding='utf-8') as f:
        md_content = f.read()

    # Get title from first # heading
    title_match = re.search(r'^# (.+)$', md_content, re.MULTILINE)
    title = title_match.group(1) if title_match else md_file.stem

    # Convert to HTML
    html_content = markdown_to_html(md_content)

    # Generate full page
    page_name = md_file.stem
    html_page = generate_html_page(title, html_content, page_name)

    # Write HTML file
    html_file = HTML_DIR / f"{page_name}.html"
    with open(html_file, 'w', encoding='utf-8') as f:
        f.write(html_page)

    print(f"✓ Generated {html_file.name}")

def main():
    """Main function"""
    print("CloudFramework API Reference HTML Generator")
    print("=" * 50)

    # Ensure output directory exists
    HTML_DIR.mkdir(parents=True, exist_ok=True)

    # Get all markdown files
    md_files = sorted(MD_DIR.glob('*.md'))

    if not md_files:
        print("No markdown files found!")
        return

    print(f"Found {len(md_files)} markdown files\n")

    # Process each file
    for md_file in md_files:
        try:
            process_file(md_file)
        except Exception as e:
            print(f"✗ Error processing {md_file.name}: {e}")

    print("\n" + "=" * 50)
    print(f"Completed! Generated {len(md_files)} HTML files in:")
    print(f"  {HTML_DIR}")

if __name__ == '__main__':
    main()
