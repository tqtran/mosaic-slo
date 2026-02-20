-- Analyze staging table structures to design real schema
-- Run this to see what data we actually have

-- SLO_CSLO Table Structure
SELECT 'SLO_CSLO COLUMNS' AS info;
DESCRIBE slo_cslo;

SELECT 'SLO_CSLO SAMPLE DATA' AS info;
SELECT * FROM slo_cslo LIMIT 5;

SELECT 'SLO_CSLO COUNTS' AS info;
SELECT 
    COUNT(*) AS total_rows,
    COUNT(DISTINCT `Program`) AS unique_programs,
    COUNT(DISTINCT `Discipline`) AS unique_disciplines,
    COUNT(DISTINCT `SUBJ`) AS unique_subjects,
    COUNT(DISTINCT `CRS_ID`) AS unique_courses,
    COUNT(DISTINCT `CSLO`) AS unique_cslos
FROM slo_cslo;

-- SLO_IMPORT Table Structure  
SELECT 'SLO_IMPORT COLUMNS' AS info;
DESCRIBE slo_import;

SELECT 'SLO_IMPORT SAMPLE DATA' AS info;
SELECT * FROM slo_import LIMIT 5;

SELECT 'SLO_IMPORT COUNTS' AS info;
SELECT 
    COUNT(*) AS total_rows,
    COUNT(DISTINCT `Academic Year`) AS unique_academic_years,
    COUNT(DISTINCT `Term`) AS unique_terms,
    COUNT(DISTINCT `StudentID`) AS unique_students,
    COUNT(DISTINCT `CRN`) AS unique_crns,
    COUNT(DISTINCT `Course`) AS unique_courses,
    COUNT(DISTINCT `Program`) AS unique_programs,
    COUNT(DISTINCT `CSLO`) AS unique_cslos
FROM slo_import;
