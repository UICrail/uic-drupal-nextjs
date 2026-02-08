#!/usr/bin/env python3
"""
Script to harmonize date formats in project_page.xml
Converts all date formats to DD/MM/YYYY
"""

import re
from datetime import datetime

def parse_date(date_str):
    """
    Parse various date formats and return DD/MM/YYYY format
    """
    date_str = date_str.strip()
    
    # Already in DD/MM/YYYY format
    if re.match(r'^\d{2}/\d{2}/\d{4}$', date_str):
        return date_str
    
    # Format: DD.MM.YYYY (with dots)
    match = re.match(r'^(\d{2})\.(\d{2})\.(\d{4})$', date_str)
    if match:
        day, month, year = match.groups()
        return f"{day}/{month}/{year}"
    
    # Format: D.M.YYYY or DD.M.YYYY or D.MM.YYYY (with dots, single digits)
    match = re.match(r'^(\d{1,2})\.(\d{1,2})\.(\d{4})$', date_str)
    if match:
        day, month, year = match.groups()
        return f"{day.zfill(2)}/{month.zfill(2)}/{year}"
    
    # Format: DD/MM/YY (2-digit year)
    match = re.match(r'^(\d{2})/(\d{2})/(\d{2})$', date_str)
    if match:
        day, month, year = match.groups()
        # Assume 20xx for years
        return f"{day}/{month}/20{year}"
    
    # Format: D/MM/YYYY or DD/M/YYYY (single digit day or month)
    match = re.match(r'^(\d{1,2})/(\d{1,2})/(\d{4})$', date_str)
    if match:
        day, month, year = match.groups()
        return f"{day.zfill(2)}/{month.zfill(2)}/{year}"
    
    # Format: MM/YYYY (only month and year)
    match = re.match(r'^(\d{2})/(\d{4})$', date_str)
    if match:
        month, year = match.groups()
        return f"01/{month}/{year}"
    
    # Format: "D Month YYYY" or "DD Month YYYY" (e.g., "1 October 2023")
    months = {
        'january': '01', 'february': '02', 'march': '03', 'april': '04',
        'may': '05', 'june': '06', 'july': '07', 'august': '08',
        'september': '09', 'october': '10', 'november': '11', 'december': '12',
        'janvier': '01', 'février': '02', 'fevrier': '02', 'mars': '03', 'avril': '04',
        'mai': '05', 'juin': '06', 'juillet': '07', 'août': '08', 'aout': '08',
        'septembre': '09', 'octobre': '10', 'novembre': '11', 'décembre': '12', 'decembre': '12'
    }
    
    match = re.match(r'^(\d{1,2})\s+([a-zA-Zéû]+)\s+(\d{4})$', date_str, re.IGNORECASE)
    if match:
        day, month_name, year = match.groups()
        month = months.get(month_name.lower())
        if month:
            return f"{day.zfill(2)}/{month}/{year}"
    
    # Format: "Month YYYY" (e.g., "October 2023")
    match = re.match(r'^([a-zA-Zéû]+)\s+(\d{4})$', date_str, re.IGNORECASE)
    if match:
        month_name, year = match.groups()
        month = months.get(month_name.lower())
        if month:
            return f"01/{month}/{year}"
    
    # Format: YYYY (only year)
    match = re.match(r'^(\d{4})$', date_str)
    if match:
        year = match.group(1)
        return f"01/01/{year}"
    
    # Format: YYYY-MM-DD (ISO format)
    match = re.match(r'^(\d{4})-(\d{2})-(\d{2})$', date_str)
    if match:
        year, month, day = match.groups()
        return f"{day}/{month}/{year}"
    
    # If nothing matches, return original
    print(f"Warning: Could not parse date: '{date_str}'")
    return date_str

def harmonize_dates_in_line(line):
    """
    Find and harmonize dates in a line containing Project start/end date
    """
    # Pattern to match date lines
    patterns = [
        (r'(-\* Project start date:\s*)(.+?)(\s*$)', 'start'),
        (r'(-\* Project end date:\s*)(.+?)(\s*$)', 'end'),
        (r'(-\* Duration:\s*from\s+)([^t]+?)(\s+to\s+)(.+?)(\s*$)', 'range'),
    ]
    
    for pattern_info in patterns:
        if pattern_info[1] == 'range':
            pattern, ptype = pattern_info[0], pattern_info[1]
            match = re.search(pattern, line)
            if match:
                prefix = match.group(1)
                start_date = match.group(2)
                middle = match.group(3)
                end_date = match.group(4)
                suffix = match.group(5)
                
                harmonized_start = parse_date(start_date)
                harmonized_end = parse_date(end_date)
                
                new_line = line[:match.start()] + prefix + harmonized_start + middle + harmonized_end + suffix + line[match.end():]
                return new_line
        else:
            pattern, ptype = pattern_info[0], pattern_info[1]
            match = re.search(pattern, line)
            if match:
                prefix = match.group(1)
                date_str = match.group(2)
                suffix = match.group(3)
                
                harmonized = parse_date(date_str)
                
                new_line = line[:match.start()] + prefix + harmonized + suffix + line[match.end():]
                return new_line
    
    return line

def harmonize_dates_in_file(input_file, output_file):
    """
    Read XML file and harmonize all dates
    """
    with open(input_file, 'r', encoding='utf-8') as f:
        lines = f.readlines()
    
    modified_lines = []
    changes_count = 0
    
    for line in lines:
        # Check if line contains date-related keywords
        if 'Project start date:' in line or 'Project end date:' in line or ('Duration:' in line and 'from' in line):
            original_line = line
            new_line = harmonize_dates_in_line(line)
            if new_line != original_line:
                changes_count += 1
                print(f"Changed: {original_line.strip()} -> {new_line.strip()}")
            modified_lines.append(new_line)
        else:
            modified_lines.append(line)
    
    with open(output_file, 'w', encoding='utf-8') as f:
        f.writelines(modified_lines)
    
    print(f"\nTotal changes made: {changes_count}")
    print(f"Output written to: {output_file}")

if __name__ == "__main__":
    import os
    
    # Get the directory where the script is located
    script_dir = os.path.dirname(os.path.abspath(__file__))
    input_file = os.path.join(script_dir, "project_page.xml")
    output_file = os.path.join(script_dir, "project_page_harmonized.xml")
    
    print("Starting date harmonization...")
    print(f"Input file: {input_file}")
    print(f"Output file: {output_file}")
    harmonize_dates_in_file(input_file, output_file)
    print("Done!")
