# Moodle — PDF Accessibility Tools 
## Overview 
This repository contains customizations and plugins for PDF management and accessibility within Moodle. It focuses on two admin areas and two course blocks that help administrators and teachers inspect, count and manage PDF resources and accessibility-related data.

##Directory Summary
- admin/pdfaccessibility : Admin dashboard for accessibility management. Administrators can filter by department, course or subject and visualize accessibility progress across the site. The dashboard shows PDFs with errors, courses/disciplines with the highest and lowest accessibility percentages, and the most common PDF errors. 

- blocks/pdfaccessibility : Real‑time PDF accessibility checker integrated into Moodle's upload page. When a teacher uploads a PDF, the block evaluates the file immediately and displays detected issues and suggested corrections within the upload UI, so the teacher can fix problems before publishing.

- blocks/pdfcounter : Course front‑page dashboard that summarizes overall accessibility for the course. Shows the list of PDFs and how many errors each contains, offers the ability to download a report, and includes historical trends to display the course's accessibility progress over time. 
