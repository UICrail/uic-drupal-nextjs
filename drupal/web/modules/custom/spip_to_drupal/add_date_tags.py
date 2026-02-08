#!/usr/bin/env python3
"""
Script to add <start>, <end> and <members> tags after <chapo> in project_page.xml
Extracts dates and company IDs from the text content and adds them as separate XML tags
"""

import re
import os

def parse_date(date_str):
    """
    Parse various date formats and return DD/MM/YYYY format
    """
    if not date_str:
        return None
    
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
    
    # Format: "D Month YYYY" or "DD Month YYYY"
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
    
    return None

def extract_dates_from_text(text):
    """
    Extract start and end dates from project text
    Returns tuple (start_date, end_date) in DD/MM/YYYY format
    """
    start_date = None
    end_date = None
    
    # Look for "Project start date:" pattern
    match = re.search(r'-\*\s+Project start date:\s*(?:from\s+)?([^\n]+?)(?:\s+to\s+([^\n]+?))?(?=\n|-\*|$)', text)
    if match:
        start_str = match.group(1).strip()
        # Check if there's a "to" part in the same line
        if match.group(2):
            end_str = match.group(2).strip()
            end_date = parse_date(end_str)
        
        start_date = parse_date(start_str)
    
    # Look for "Project end date:" pattern
    match = re.search(r'-\*\s+Project end date:\s*([^\n]+)', text)
    if match:
        end_str = match.group(1).strip()
        end_date = parse_date(end_str)
    
    # Look for "Duration: from X to Y" pattern if not found above
    if not start_date or not end_date:
        match = re.search(r'Duration:.*?from\s+([^\n]+?)\s+to\s+([^\n]+?)(?=\n|-\*|$)', text)
        if match:
            if not start_date:
                start_date = parse_date(match.group(1).strip())
            if not end_date:
                end_date = parse_date(match.group(2).strip())
    
    return start_date, end_date

def extract_company_ids(text):
    """
    Extract all company IDs from <companyXX> tags in the text
    Returns a sorted list of unique company IDs
    """
    # Find all <companyXXX> tags
    company_pattern = r'<company(\d+)>'
    matches = re.findall(company_pattern, text)
    
    # Convert to integers, remove duplicates, and sort
    company_ids = sorted(set(int(id) for id in matches if id))
    
    return company_ids

def add_date_tags(xml_content):
    """
    Add <start>, <end> and <members> tags after each <chapo></chapo> in the XML
    """
    # Split content into rubriques
    rubrique_pattern = r'(<rubrique xml:id="[^"]+">.*?</rubrique>)'
    rubriques = re.finditer(rubrique_pattern, xml_content, re.DOTALL)
    
    modified_content = xml_content
    offset = 0
    changes_count = 0
    
    for rubrique_match in re.finditer(rubrique_pattern, xml_content, re.DOTALL):
        rubrique_content = rubrique_match.group(1)
        
        # Extract the texte content to find dates and companies
        texte_match = re.search(r'<texte><!\[CDATA\[(.*?)\]\]></texte>', rubrique_content, re.DOTALL)
        if not texte_match:
            continue
        
        texte_content = texte_match.group(1)
        start_date, end_date = extract_dates_from_text(texte_content)
        company_ids = extract_company_ids(texte_content)
        
        # Only proceed if we found at least a start date
        if not start_date:
            continue
        
        # Find the </chapo> tag in this rubrique
        chapo_match = re.search(r'<chapo>.*?</chapo>', rubrique_content, re.DOTALL)
        if not chapo_match:
            continue
        
        # Build the date tags to insert
        date_tags = f"\n\t\t\t\t<start>{start_date}</start>"
        if end_date:
            date_tags += f"\n\t\t\t\t<end>{end_date}</end>"
        
        # Add members tag if company IDs were found
        if company_ids:
            members_list = ','.join(str(id) for id in company_ids)
            date_tags += f"\n\t\t\t\t<members>{members_list}</members>"
        
        # Calculate position to insert (after </chapo>)
        insert_pos = rubrique_match.start() + chapo_match.end() + offset
        
        # Insert the date tags
        modified_content = modified_content[:insert_pos] + date_tags + modified_content[insert_pos:]
        offset += len(date_tags)
        changes_count += 1
        
        # Extract project title for reporting
        titre_match = re.search(r'<titre><!\[CDATA\[(.*?)\]\]></titre>', rubrique_content)
        titre = titre_match.group(1) if titre_match else "Unknown"
        
        # Format company IDs for display
        members_display = f", members=[{','.join(str(id) for id in company_ids)}]" if company_ids else ""
        print(f"✓ {titre}: start={start_date}, end={end_date or 'N/A'}{members_display}")
    
    return modified_content, changes_count

def main():
    script_dir = os.path.dirname(os.path.abspath(__file__))
    input_file = os.path.join(script_dir, "project_page.xml")
    output_file = os.path.join(script_dir, "project_page_with_dates.xml")
    log_file = os.path.join(script_dir, "add_date_tags.log")
    
    # Open log file
    log = open(log_file, 'w', encoding='utf-8')
    
    def log_print(msg):
        print(msg)
        log.write(msg + '\n')
        log.flush()
    
    log_print("=" * 70)
    log_print("DATE TAGS & MEMBERS ADDITION SCRIPT")
    log_print("=" * 70)
    log_print(f"Input file:  {input_file}")
    log_print(f"Output file: {output_file}")
    log_print("=" * 70)
    log_print("")
    
    # Read the entire XML file
    try:
        with open(input_file, 'r', encoding='utf-8') as f:
            content = f.read()
        log_print(f"✓ File read successfully: {len(content)} bytes")
    except Exception as e:
        log_print(f"Error reading file: {e}")
        log.close()
        return 1
    
    # Process the content
    log_print("Processing projects...")
    log_print("-" * 70)
    modified_content, changes_count = add_date_tags(content)
    log_print("-" * 70)
    
    # Write the modified content
    try:
        with open(output_file, 'w', encoding='utf-8') as f:
            f.write(modified_content)
        log_print(f"✓ File written successfully: {len(modified_content)} bytes")
    except Exception as e:
        log_print(f"Error writing file: {e}")
        log.close()
        return 1
    
    log_print("")
    log_print("=" * 70)
    log_print(f"✓ Total projects processed: {changes_count}")
    log_print(f"✓ Output written to: {output_file}")
    log_print("=" * 70)
    
    log.close()
    return 0

if __name__ == "__main__":
    exit(main())
