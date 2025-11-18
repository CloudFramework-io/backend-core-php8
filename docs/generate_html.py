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

# Sidebar navigation (consistent with index.html and guides)
SIDEBAR_NAV = '''            <nav class="sidebar-nav">
                <!-- Getting Started -->
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Getting Started</div>
                    <ul>
                        <li><a href="../index.html" class="sidebar-nav-link">Documentation Home</a></li>
                        <li><a href="../guides/getting-started.html" class="sidebar-nav-link">Installation & Setup</a></li>
                        <li><a href="../guides/getting-started.html#quick-start" class="sidebar-nav-link">Quick Start</a></li>
                    </ul>
                </div>

                <!-- Development Guides -->
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Development Guides</div>
                    <ul>
                        <li><a href="../guides/api-development.html" class="sidebar-nav-link">API Development</a></li>
                        <li><a href="../guides/script-development.html" class="sidebar-nav-link">Script Development</a></li>
                        <li><a href="../guides/configuration.html" class="sidebar-nav-link">Configuration</a></li>
                        <li><a href="../guides/deployment.html" class="sidebar-nav-link">Deployment</a></li>
                    </ul>
                </div>

                <!-- GCP Integration -->
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">GCP Integration</div>
                    <ul>
                        <li><a href="../guides/gcp-integration.html" class="sidebar-nav-link">GCP Overview</a></li>
                        <li><a href="../guides/security.html" class="sidebar-nav-link">Security</a></li>
                        <li><a href="../guides/testing.html" class="sidebar-nav-link">Testing</a></li>
                    </ul>
                </div>

                <!-- Core Classes -->
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Core Classes</div>
                    <ul>
                        <li><a href="Core7.html" class="sidebar-nav-link">Core7</a></li>
                        <li><a href="RESTful.html" class="sidebar-nav-link">RESTful</a></li>
                        <li><a href="Scripts2020.html" class="sidebar-nav-link">Scripts2020</a></li>
                    </ul>
                </div>

                <!-- Configuration Classes -->
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Configuration</div>
                    <ul>
                        <li><a href="CoreConfig.html" class="sidebar-nav-link">CoreConfig</a></li>
                        <li><a href="CoreCache.html" class="sidebar-nav-link">CoreCache</a></li>
                        <li><a href="CoreSession.html" class="sidebar-nav-link">CoreSession</a></li>
                        <li><a href="CoreSecurity.html" class="sidebar-nav-link">CoreSecurity</a></li>
                    </ul>
                </div>

                <!-- Data Access Classes -->
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Data Access</div>
                    <ul>
                        <li><a href="DataStore.html" class="sidebar-nav-link">DataStore</a></li>
                        <li><a href="Buckets.html" class="sidebar-nav-link">Buckets</a></li>
                        <li><a href="DataBQ.html" class="sidebar-nav-link">DataBQ</a></li>
                        <li><a href="CloudSQL.html" class="sidebar-nav-link">CloudSQL</a></li>
                        <li><a href="DataSQL.html" class="sidebar-nav-link">DataSQL</a></li>
                        <li><a href="DataMongoDB.html" class="sidebar-nav-link">DataMongoDB</a></li>
                    </ul>
                </div>

                <!-- Utilities -->
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Utilities</div>
                    <ul>
                        <li><a href="Email.html" class="sidebar-nav-link">Email</a></li>
                        <li><a href="DataValidation.html" class="sidebar-nav-link">DataValidation</a></li>
                        <li><a href="WorkFlows.html" class="sidebar-nav-link">WorkFlows</a></li>
                        <li><a href="GoogleSecrets.html" class="sidebar-nav-link">GoogleSecrets</a></li>
                        <li><a href="PubSub.html" class="sidebar-nav-link">PubSub</a></li>
                    </ul>
                </div>

                <!-- Examples -->
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Examples</div>
                    <ul>
                        <li><a href="../examples/api-examples.html" class="sidebar-nav-link">API Examples</a></li>
                        <li><a href="../examples/script-examples.html" class="sidebar-nav-link">Script Examples</a></li>
                    </ul>
                </div>
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

    # Tables - convert markdown tables to HTML
    def convert_table(match):
        lines = match.group(0).strip().split('\n')
        if len(lines) < 3:
            return match.group(0)

        # Header
        headers = [cell.strip() for cell in lines[0].split('|') if cell.strip()]

        # Rows (skip separator line at index 1)
        rows = []
        for line in lines[2:]:
            if '|' in line:
                cells = [cell.strip() for cell in line.split('|') if cell.strip()]
                if cells:  # Only add non-empty rows
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

    # Match table pattern: line with pipes, followed by separator line, followed by more lines with pipes
    html = re.sub(r'\|[^\n]+\|\n\|[-:\s|]+\|\n(?:\|[^\n]+\|\n?)+', convert_table, html)

    # Paragraphs (lines that don't start with tags)
    # We need to be more careful about what constitutes a block-level element
    lines = html.split('\n')
    result = []
    paragraph = []
    in_block_element = False
    block_tags = ['<pre>', '<ul>', '<ol>', '<table>', '<h1>', '<h2>', '<h3>', '<h4>', '<h5>', '<h6>', '<div>', '<hr>']
    closing_block_tags = ['</pre>', '</ul>', '</ol>', '</table>', '</div>']

    for line in lines:
        stripped = line.strip()

        # Empty line
        if not stripped:
            if paragraph and not in_block_element:
                result.append('<p>' + ' '.join(paragraph) + '</p>')
                paragraph = []
            result.append('')
            continue

        # Check if entering or exiting a block element
        starts_block = any(tag in stripped for tag in block_tags)
        ends_block = any(tag in stripped for tag in closing_block_tags)

        # Check if line starts with HTML tag
        if re.match(r'^\s*<', stripped):
            # Flush paragraph before block element
            if paragraph and not in_block_element:
                result.append('<p>' + ' '.join(paragraph) + '</p>')
                paragraph = []

            # Update block element tracking
            if starts_block:
                in_block_element = True
            if ends_block:
                in_block_element = False

            result.append(line)
        else:
            # Regular text - only add to paragraph if not in block element
            if not in_block_element:
                paragraph.append(stripped)
            else:
                # Inside block element, preserve as-is
                result.append(line)

    # Flush remaining paragraph
    if paragraph and not in_block_element:
        result.append('<p>' + ' '.join(paragraph) + '</p>')

    html = '\n'.join(result)

    # Clean up excessive blank lines
    html = re.sub(r'\n{3,}', '\n\n', html)

    return html

def generate_html_page(title, content, active_nav):
    """Generate complete HTML page"""

    # Update active nav link to add 'active' class
    sidebar = SIDEBAR_NAV.replace(
        f'<a href="{active_nav}.html" class="sidebar-nav-link">',
        f'<a href="{active_nav}.html" class="sidebar-nav-link active">'
    )

    html = f'''<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{title} - CloudFramework Backend Core PHP8</title>
    <meta name="description" content="{title} - CloudFramework Backend Core PHP8 API Reference">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <h1>CloudFramework Backend Core PHP8</h1>
        <span class="version">v8.4+</span>
    </header>

    <!-- Container -->
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-search">
                <input type="text" id="search-input" placeholder="Search documentation...">
            </div>

{sidebar}
        </aside>

        <!-- Main Content -->
        <main class="main-content">
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
