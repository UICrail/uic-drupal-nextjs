#!/usr/bin/env python3
"""
Script to harmonize date formats in project_page.xml
Converts all date formats to DD/MM/YYYY
"""

import re
import sys

def parse_date(date_str):
    """
    Parse various date formats and return DD/MM/YYYY format
    """
    date_str = date_str.strip()
    
    # Already in DD/MM/YYYY format
    if re.match(r'^\d{2}/\d{2}/\d{4}$', date_str):
        return date_str
    
    # Format: DD.MM.YYYY (with dots)
    match = re.match(r'^(\d{1,2})\.(\d{1,2})\.(\d{4})$', date_str)
    if match:
        day, month, year = match.groups()
        return f"{day.zfill(2)}/{month.zfill(2)}/{year}"
    
    # Format: D/MM/YYYY or DD/M/YYYY (single digit day or month)
    match = re.match(r'^(\d{1,2})/(\d{1,2})/(\d{4})$', date_str)
    if match:
        day, month, year = match.groups()
        return f"{day.zfill(2)}/{month.zfill(2)}/{year}"
    
    # Format: MM/YYYY (only month and year) - set day to 01
    match = re.match(r'^(\d{2})/(\d{4})$', date_str)
    if match:
        month, year = match.groups()
        return f"01/{month}/{year}"
    
    # Format: "D Month YYYY" or "DD Month YYYY" (e.g., "1 October 2023")
    months = {
        'january': '01', 'february': '02', 'march': '03', 'april': '04',
        'may': '05', 'june': '06', 'july': '07', 'august': '08',
        'september': '09', 'october': '10', 'november': '11', 'december': '12'
    }
    
    match = re.match(r'^(\d{1,2})\s+([a-zA-Z]+)\s+(\d{4})$', date_str, re.IGNORECASE)
    if match:
        day, month_name, year = match.groups()
        month = months.get(month_name.lower())
        if month:
            return f"{day.zfill(2)}/{month}/{year}"
    
    # Format: YYYY-MM-DD (ISO format)
    match = re.match(r'^(\d{4})-(\d{2})-(\d{2})$', date_str)
    if match:
        year, month, day = match.groups()
        return f"{day}/{month}/{year}"
    
    # If nothing matches, return original
    return date_str

def harmonize_dates_in_file(input_path, output_path):
    """
    Read XML file and harmonize all dates
    """
    try:
        with open(input_path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        lines = content.splitlines(keepends=True)
        modified_lines = []
        changes_count = 0
        
        for line in lines:
            original_line = line
            
            # Pattern: "-* Project start date: <date>"
            match = re.search(r'(-\*\s+Project start date:\s*)([^\n]+)', line)
            if match:
                prefix = match.group(1)
                date_part = match.group(2).strip()
                harmonized = parse_date(date_part)
                if harmonized != date_part:
                    line = line[:match.start()] + prefix + harmonized + '\n'
                    changes_count += 1
                    print(f"Start date: '{date_part}' -> '{harmonized}'")
            
            # Pattern: "-* Project end date: <date>"
            match = re.search(r'(-\*\s+Project end date:\s*)([^\n]+)', line)
            if match:
                prefix = match.group(1)
                date_part = match.group(2).strip()
                harmonized = parse_date(date_part)
                if harmonized != date_part:
                    line = line[:match.start()] + prefix + harmonized + '\n'
                    changes_count += 1
                    print(f"End date: '{date_part}' -> '{harmonized}'")
            
            # Pattern: "from <date> to <date>"
            match = re.search(r'(from\s+)([^t]+?)(\s+to\s+)(.+?)(?=\n|$)', line)
            if match and 'Project' in line:
                prefix = line[:match.start(1)]
                from_word = match.group(1)
                start_date = match.group(2).strip()
                to_word = match.group(3)
                end_date = match.group(4).strip()
                
                harmonized_start = parse_date(start_date)
                harmonized_end = parse_date(end_date)
                
                if harmonized_start != start_date or harmonized_end != end_date:
                    line = prefix + from_word + harmonized_start + to_word + harmonized_end + '\n'
                    changes_count += 1
                    print(f"Range: '{start_date} to {end_date}' -> '{harmonized_start} to {harmonized_end}'")
            
            modified_lines.append(line)
        
        with open(output_path, 'w', encoding='utf-8') as f:
            f.writelines(modified_lines)
        
        print(f"\n✓ Total changes made: {changes_count}")
        print(f"✓ Output written to: {output_path}")
        
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    import os
    
    # Get the directory where the script is located
    script_dir = os.path.dirname(os.path.abspath(__file__))
    input_file = os.path.join(script_dir, "project_page.xml")
    output_file = os.path.join(script_dir, "project_page_harmonized.xml")
    
    print("=" * 60)
    print("DATE HARMONIZATION SCRIPT")
    print("=" * 60)
    print(f"Input file: {input_file}")
    print(f"Output file: {output_file}")
    print("=" * 60)
    
    harmonize_dates_in_file(input_file, output_file)
    print("=" * 60)
    print("✓ Done!")
    print("=" * 60)
