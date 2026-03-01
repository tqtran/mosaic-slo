# VPAT Directory

This directory contains Voluntary Product Accessibility Templates (VPATs) for MOSAIC components.

## What is a VPAT?

A VPAT (Voluntary Product Accessibility Template) is a standardized document format used to communicate how well a product or service conforms to accessibility standards. Organizations, especially government agencies and educational institutions, often require VPATs during procurement to ensure products meet accessibility requirements.

## Standards Covered

Our VPATs document conformance to:

- **WCAG 2.2** (Web Content Accessibility Guidelines) - Levels A, AA, and AAA
- **Section 508** (U.S. federal accessibility standards)
- **EN 301 549** (European accessibility standard for ICT products and services)

## Available VPATs

### [Assessment Page VPAT](assessment-page-vpat.md)

**Component:** LTI Assessment Entry Interface  
**Status:** WCAG 2.2 Level AA Compliant + Partial Level AAA  
**Last Updated:** February 28, 2026

**Summary:**
- [OK] Full Level A conformance (35/35 applicable criteria)
- [OK] Full Level AA conformance (25/25 applicable criteria)
- [OK] Substantial Level AAA conformance (38/47 full support, 5 partial support)

**Key Features:**
- Enhanced contrast ratios (8.59:1)
- Large touch targets (44x44px)
- Complete keyboard navigation
- Screen reader compatible
- Motion reduction support
- Confirmation dialogs for bulk actions

### [Admin Interface VPAT](admin-interface-vpat.md)

**Component:** Administrative Dashboard and CRUD Pages  
**Status:** WCAG 2.2 Level AA Compliant + Partial Level AAA  
**Last Updated:** March 1, 2026

**Summary:**
- [OK] Full Level A conformance (30/30 applicable criteria)
- [OK] Full Level AA conformance (24/24 applicable criteria)
- [OK] Substantial Level AAA conformance (25/35 full support, 7 partial support)

**Pages Covered:**
- Shared layout (header, sidebar, footer)
- Dashboard with statistics
- 11 CRUD management pages (students, programs, courses, terms, sections, outcomes, enrollment, assessments, users)

**Key Features:**
- Enhanced contrast ratios (8.59:1)
- Large touch targets (44x44px minimum)
- Complete keyboard navigation with skip links
- Screen reader compatible with ARIA landmarks
- Consistent navigation patterns
- Error prevention with confirmation dialogs

## Implementation Guide

### [Admin Accessibility Guide](admin-accessibility-guide.md)

**Developer Resource:** Implementation patterns and code examples  
**Purpose:** Ensure all new admin pages maintain WCAG 2.2 Level AA compliance  
**Last Updated:** March 1, 2026

**Contents:**
- Accessibility checklist for admin pages
- Code patterns for common components
- DataTables accessibility requirements
- Modal dialog accessibility
- Form validation patterns
- ARIA implementation examples

## Using These VPATs

### For Procurement Officers
Review the VPAT to verify the product meets your organization's accessibility requirements before purchasing or adopting.

### For Accessibility Coordinators
Use the VPAT to:
- Assess compliance with institutional accessibility policies
- Identify any accommodations needed for specific users
- Plan accessibility testing and validation

### For Developers
Use the VPAT to:
- Understand implemented accessibility features
- Identify areas for improvement
- Maintain accessibility during updates
- Document accessibility decisions

Refer to the [Admin Accessibility Guide](admin-accessibility-guide.md) for implementation patterns and code examples.

## VPAT Updates

VPATs should be updated:
- When significant features are added or changed
- When accessibility standards are updated
- At least annually for active products
- Before major releases

## Questions or Issues?

If you have questions about the accessibility of MOSAIC components or need clarification on any VPAT content, please contact the MOSAIC development team.

## Resources

- [WCAG 2.2 Guidelines](https://www.w3.org/WAI/WCAG22/quickref/)
- [Section 508 Standards](https://www.section508.gov/)
- [EN 301 549 Standard](https://www.etsi.org/deliver/etsi_en/301500_301599/301549/)
- [ITI VPAT Templates](https://www.itic.org/policy/accessibility/vpat)
