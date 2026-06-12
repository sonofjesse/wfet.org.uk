#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { marked } = require('marked');

// Configure marked for better code highlighting and formatting
marked.setOptions({
    gfm: true,
    breaks: false,
    pedantic: false,
    sanitize: false,
    smartLists: true,
    smartypants: false,
    highlight: function(code, lang) {
        return `<pre><code class="language-${lang || 'text'}">${escapeHtml(code)}</code></pre>`;
    }
});

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Custom renderer for better styling
const renderer = new marked.Renderer();

// Custom heading renderer with anchor links
renderer.heading = function(text, level) {
    const id = text.toLowerCase().replace(/[^\w]+/g, '-');
    return `<h${level} id="${id}">
        ${text}
    </h${level}>`;
};

// Custom code block renderer
renderer.code = function(code, language) {
    const lang = language || 'text';
    return `<div class="code-block">
        <div class="code-header">
            <span>${lang}</span>
            <button class="copy-btn" onclick="copyCode(this)">Copy</button>
        </div>
        <pre><code class="language-${lang}">${escapeHtml(code)}</code></pre>
    </div>`;
};

// Custom blockquote renderer
renderer.blockquote = function(quote) {
    return `<blockquote>${quote}</blockquote>`;
};

// Custom list renderer
renderer.list = function(body, ordered) {
    const type = ordered ? 'ol' : 'ul';
    return `<${type}>${body}</${type}>`;
};

// Custom table renderer
renderer.table = function(header, body) {
    return `<table>
        <thead>${header}</thead>
        <tbody>${body}</tbody>
    </table>`;
};

marked.use({ renderer });

function buildDocumentation() {
    console.log('🔨 Building documentation...');
    
    const rootDir = path.dirname(__dirname);
    const readmePath = path.join(rootDir, 'README.md');
    // Output to website root (3 levels up from theme directory to get to /public)
    const websiteRoot = path.resolve(rootDir, '../../../');
    const outputPath = path.join(websiteRoot, 'readme.html');
    
    try {
        // Read README.md
        if (!fs.existsSync(readmePath)) {
            throw new Error('README.md not found');
        }
        
        const markdownContent = fs.readFileSync(readmePath, 'utf8');
        
        // Convert markdown to HTML
        let htmlContent = marked(markdownContent);
        
        // Remove specific H1 and H2 elements from the main content
        // Remove H1 with id="theme-documentation" 
        htmlContent = htmlContent.replace(/<h1[^>]*id="theme-documentation"[^>]*>.*?<\/h1>/gi, '');
        // Remove H2 with id="features"
        htmlContent = htmlContent.replace(/<h2[^>]*id="features"[^>]*>.*?<\/h2>/gi, '');
        // Remove any remaining H1 elements (should be the main title)
        htmlContent = htmlContent.replace(/<h1[^>]*>.*?<\/h1>/gi, '');
        // Remove the first H2 (should be the description paragraph after the title)
        htmlContent = htmlContent.replace(/<h2[^>]*>.*?<\/h2>/gi, '');
        
        // Read package.json for theme info
        const packagePath = path.join(rootDir, 'package.json');
        const packageJson = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
        
        // Use hardcoded site title
        const siteTitle = 'SOJ Core 2025 WooCommerce';
        
        // Create the complete HTML document
        const htmlDocument = createHtmlDocument(htmlContent, packageJson, siteTitle);
        
        // Write the HTML file
        fs.writeFileSync(outputPath, htmlDocument);
        
        console.log(`✅ Documentation generated: ${path.basename(outputPath)}`);
        console.log(`📖 Open ${outputPath} in your browser to view the documentation`);
        console.log(`🌐 Or visit: http://localhost:10298/${path.basename(outputPath)}`);
        
    } catch (error) {
        console.error('❌ Error building documentation:', error.message);
        process.exit(1);
    }
}

function createHtmlDocument(content, packageInfo, siteTitle) {
    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${packageInfo.name} - Documentation</title>
    <style>
        ${getDocumentationStyles()}
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <h1>${siteTitle}</h1>
            <p>${packageInfo.description}</p>
            <div class="meta-info">
                <span class="meta-tag">v${packageInfo.version}</span>
                <span class="meta-tag">${packageInfo.license}</span>
                <span class="meta-tag">Updated: ${new Date().toLocaleDateString()}</span>
            </div>
        </div>
    </header>
    
    <div class="container">
        <div class="main-layout">
            <div class="sidebar">
                <h3>Table of Contents</h3>
                <div id="toc-container"></div>
            </div>
            
            <div class="content">
                ${content}
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <div class="container">
            <p>Generated from README.md • ${new Date().toLocaleString()}</p>
        </div>
    </footer>
    
    <script>
        ${getDocumentationScript()}
    </script>
</body>
</html>`;
}

function getDocumentationStyles() {
    return `
        /* Clean Documentation Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #000;
            background: #fff;
            padding: 0;
            margin: 0;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header */
        .header {
            background: #fff;
            color: #000;
            padding: 40px 0;
            margin-bottom: 0;
            border-bottom: 1px solid #000;
        }
        
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #000;
        }
        
        .header p {
            font-size: 1.125rem;
            color: #666;
            margin-bottom: 20px;
        }
        
        .meta-info {
            display: flex;
            gap: 15px;
            font-size: 0.875rem;
            flex-wrap: wrap;
        }
        
        .meta-tag {
            background: #f5f5f5;
            color: #000;
            padding: 5px 12px;
            border-radius: 6px;
            border: 1px solid #000;
        }
        
        /* Main Layout */
        .main-layout {
            display: flex;
            gap: 30px;
            margin: 30px 0;
        }
        
        /* Navigation */
        .sidebar {
            width: 30%;
            background: #f5f5f5;
            border: 1px solid #000;
            border-radius: 8px;
            padding: 15px;
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .sidebar h3 {
            margin-bottom: 12px;
            color: #000;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .toc {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .toc li {
            margin: 0;
            padding: 0;
        }
        
        .toc a {
            display: block;
            padding: 6px 12px;
            color: #666;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            line-height: 1.4;
            border-radius: 6px;
            margin: 2px 0;
        }
        
        .toc a:hover {
            color: #000;
            background: #e5e5e5;
        }
        
        /* Level 2 - Main sections (H2 only) */
        .toc .level-2 a {
            font-weight: 600;
            color: #000;
            font-size: 0.9rem;
            padding-left: 12px;
        }
        
        /* Main Content */
        .content {
            width: 70%;
            padding: 0;
        }
        
        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            margin: 30px 0 15px 0;
            line-height: 1.3;
            font-weight: 600;
        }
        
        h1 {
            font-size: 2.25rem;
            color: #000;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-top: 40px;
        }
        
        h2 {
            font-size: 1.875rem;
            color: #000;
            margin-top: 50px;
        }
        
        h3 {
            font-size: 1.5rem;
            color: #000;
        }
        
        h4 {
            font-size: 1.25rem;
            color: #000;
        }
        
        p {
            margin: 15px 0;
            color: #000;
        }
        
        /* Lists */
        ul, ol {
            margin: 15px 0;
            padding-left: 30px;
        }
        
        li {
            margin: 8px 0;
            color: #000;
        }
        
        /* Code Blocks */
        pre {
            background: #f5f5f5;
            border: 1px solid #000;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            overflow-x: auto;
            position: relative;
        }
        
        code {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        
        /* Inline Code */
        :not(pre) > code {
            background: #f5f5f5;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 0.875rem;
            color: #000;
            border: 1px solid #ccc;
        }
        
        /* Code Block with Language */
        .code-block {
            position: relative;
            margin: 20px 0;
        }
        
        .code-header {
            background: #e5e5e5;
            padding: 10px 15px;
            border-radius: 8px 8px 0 0;
            font-size: 0.875rem;
            color: #000;
            border: 1px solid #000;
            border-bottom: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .copy-btn {
            background: #000;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
        }
        
        .copy-btn:hover {
            background: #333;
        }
        
        .code-header + pre {
            margin-top: 0;
            border-radius: 0 0 8px 8px;
            border-top: none;
        }
        
        /* Blockquotes */
        blockquote {
            margin: 20px 0;
            padding: 15px 20px;
            background: #f5f5f5;
            border-left: 4px solid #000;
            border-radius: 0 8px 8px 0;
        }
        
        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border: 1px solid #000;
            border-radius: 8px;
            overflow: hidden;
        }
        
        th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 1px solid #000;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #ccc;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        /* Links */
        a {
            color: #000;
            text-decoration: underline;
        }
        
        a:hover {
            text-decoration: none;
        }
        
        /* Footer */
        .footer {
            margin-top: 60px;
            padding: 30px 0;
            background: #f5f5f5;
            border-top: 1px solid #000;
            text-align: center;
            color: #666;
            font-size: 0.875rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .meta-info {
                flex-direction: column;
                gap: 8px;
            }
            
            .main-layout {
                flex-direction: column;
                gap: 20px;
            }
            
            .sidebar {
                width: 100%;
                position: static;
            }
            
            .content {
                width: 100%;
            }
            
            h1 { font-size: 1.875rem; }
            h2 { font-size: 1.5rem; }
            h3 { font-size: 1.25rem; }
            
            pre {
                padding: 15px;
                font-size: 0.8rem;
            }
        }
        
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
    `;
}

function getDocumentationScript() {
    return `
        // Generate Table of Contents
        function generateTOC() {
            const headings = document.querySelectorAll('h2');
            const tocContainer = document.getElementById('toc-container');
            
            if (!tocContainer || headings.length === 0) return;
            
            const tocList = document.createElement('ul');
            tocList.className = 'toc';
            
            headings.forEach(heading => {
                const level = parseInt(heading.tagName.substring(1));
                if (level !== 2) return; // Only show h2 in TOC
                
                // Create an ID for the heading if it doesn't have one
                if (!heading.id) {
                    heading.id = heading.textContent.toLowerCase()
                        .replace(/[^\\w\\s]/gi, '')
                        .replace(/\\s+/g, '-');
                }
                
                const li = document.createElement('li');
                li.className = 'level-' + level;
                
                const link = document.createElement('a');
                link.href = '#' + heading.id;
                // Remove emojis and clean up text for TOC
                const cleanText = heading.textContent.trim()
                    .replace(/[\u{1F600}-\u{1F64F}]|[\u{1F300}-\u{1F5FF}]|[\u{1F680}-\u{1F6FF}]|[\u{1F1E0}-\u{1F1FF}]|[\u{2600}-\u{26FF}]|[\u{2700}-\u{27BF}]/gu, '')
                    .trim();
                link.textContent = cleanText;
                
                li.appendChild(link);
                tocList.appendChild(li);
            });
            
            tocContainer.appendChild(tocList);
        }
        
        // Copy code functionality
        function copyCode(button) {
            const codeBlock = button.closest('.code-block');
            const code = codeBlock.querySelector('code');
            const text = code.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                const originalText = button.textContent;
                button.textContent = 'Copied!';
                button.style.background = '#10b981';
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.background = '#2563eb';
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy code:', err);
            });
        }
        
        // Smooth scroll to anchors
        function setupSmoothScroll() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        }
        
        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            generateTOC();
            setupSmoothScroll();
        });
        
        // Make copyCode function globally available
        window.copyCode = copyCode;
    `;
}

// Run the build
if (require.main === module) {
    buildDocumentation();
}

module.exports = { buildDocumentation };
