// CloudFramework Documentation JavaScript

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    // Create mobile menu button
    const menuBtn = document.createElement('button');
    menuBtn.className = 'mobile-menu-btn';
    menuBtn.innerHTML = '☰';
    menuBtn.onclick = toggleMobileMenu;
    document.body.appendChild(menuBtn);

    // Search functionality
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearch);
    }

    // Highlight current page in navigation
    highlightCurrentPage();

    // Add copy buttons to code blocks
    addCopyButtons();

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
});

function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('open');
}

function handleSearch(e) {
    const searchTerm = e.target.value.toLowerCase();
    const navLinks = document.querySelectorAll('.sidebar-nav-link');

    navLinks.forEach(link => {
        const text = link.textContent.toLowerCase();
        const parent = link.parentElement;

        if (text.includes(searchTerm)) {
            parent.style.display = 'block';
        } else {
            parent.style.display = searchTerm ? 'none' : 'block';
        }
    });
}

function highlightCurrentPage() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.sidebar-nav-link');

    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPath ||
            currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('active');
        }
    });
}

function addCopyButtons() {
    const codeBlocks = document.querySelectorAll('pre');

    codeBlocks.forEach(block => {
        const button = document.createElement('button');
        button.className = 'copy-btn';
        button.textContent = 'Copy';
        button.style.cssText = `
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            padding: 0.25rem 0.75rem;
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 0.25rem;
            cursor: pointer;
            font-size: 0.75rem;
        `;

        button.addEventListener('click', () => {
            const code = block.querySelector('code');
            navigator.clipboard.writeText(code.textContent);
            button.textContent = 'Copied!';
            setTimeout(() => button.textContent = 'Copy', 2000);
        });

        block.style.position = 'relative';
        block.appendChild(button);
    });
}

// Simple syntax highlighting for PHP
function highlightPHP(code) {
    return code
        .replace(/(&lt;\?php|\?&gt;)/g, '<span class="keyword">$1</span>')
        .replace(/\b(function|class|public|private|protected|static|const|return|if|else|foreach|while|for|switch|case|break|continue|new|extends|implements|interface|namespace|use|trait|abstract|final)\b/g, '<span class="keyword">$1</span>')
        .replace(/('.*?'|".*?")/g, '<span class="string">$1</span>')
        .replace(/\/\/.*/g, '<span class="comment">$&</span>')
        .replace(/\/\*[\s\S]*?\*\//g, '<span class="comment">$&</span>');
}

// Table of Contents Generator
function generateTOC() {
    const content = document.querySelector('.main-content');
    const headings = content.querySelectorAll('h2, h3');

    if (headings.length === 0) return;

    const toc = document.createElement('div');
    toc.className = 'toc card';
    toc.innerHTML = '<h4>Table of Contents</h4><ul></ul>';

    const list = toc.querySelector('ul');

    headings.forEach(heading => {
        const li = document.createElement('li');
        const a = document.createElement('a');
        const id = heading.textContent.toLowerCase().replace(/[^a-z0-9]+/g, '-');

        heading.id = id;
        a.href = '#' + id;
        a.textContent = heading.textContent;

        if (heading.tagName === 'H3') {
            a.style.marginLeft = '1rem';
            a.style.fontSize = '0.875rem';
        }

        li.appendChild(a);
        list.appendChild(li);
    });

    const firstH2 = content.querySelector('h2');
    if (firstH2) {
        firstH2.parentNode.insertBefore(toc, firstH2);
    }
}

// Initialize TOC if on documentation page
if (document.querySelector('.main-content')) {
    document.addEventListener('DOMContentLoaded', generateTOC);
}
