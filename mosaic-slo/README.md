# MOSAIC SLO Website

This directory contains the static website files for mosaic-slo.org.

## Files

- **index.html** - Main landing page introducing MOSAIC SLO
- **faq.html** - Frequently asked questions

## Deployment

These files can be hosted on any static web server:

- GitHub Pages
- Netlify
- Vercel
- AWS S3 + CloudFront
- Traditional web hosting

## Local Preview

Open `index.html` in a web browser, or use a simple HTTP server:

```bash
# Python 3
python -m http.server 8000

# PHP
php -S localhost:8000

# Node.js (with http-server)
npx http-server
```

Then visit `http://localhost:8000`

## Customization

All styling is self-contained in each HTML file. No build process required.

- Bootstrap 5 CDN for layout and components
- Bootstrap Icons CDN for iconography
- Inline CSS for custom styling

## License

Content is part of the MOSAIC SLO project. See main repository for license details.
