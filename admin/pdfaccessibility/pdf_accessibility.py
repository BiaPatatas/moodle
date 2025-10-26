import re
import PyPDF2
from langdetect import detect
import pdfplumber
import fitz  # PyMuPDF
import pikepdf
from pdfixsdk.Pdfix import *
import requests

import sys
import json



# Functions -------------------------------------------------------------------

#Structure
def getStructTree(pdf_path: str):
    pdfix = GetPdfix()
    doc = pdfix.OpenDoc(pdf_path, "")
    if not doc:
        return None, None
    
    structTree = doc.GetStructTree()
    if not structTree:
        doc.Close()
        return None, None
    
    return doc, structTree


def extract_metadata(pdf_path):
    """Extracts metadata from the PDF."""
    with open(pdf_path, 'rb') as file:
        reader = PyPDF2.PdfReader(file)
        metadata = reader.metadata
        title = metadata.get("/Title", "No Title Found")
    
    return title

#Linguagem-------------------------------------------------------------------------------------

def get_pdf_declared_language(path):
    with pikepdf.Pdf.open(path) as pdf:
            root = pdf.trailer["/Root"]
            lang = root.get('/Lang', None)
            if lang:
                return str(lang).strip()
    


def detect_language_from_text(path):

        with fitz.open(path) as doc:
            text = "".join(page.get_text() for page in doc)
        return detect(text)
 


def normalize_lang(tag):
    if not tag:
        return None
    return tag.lower().split('-')[0]


def compare_languages(pdf_path):
    declarado = get_pdf_declared_language(pdf_path)
    detectado = detect_language_from_text(pdf_path)

    dec_norm = normalize_lang(declarado)
    det_norm = normalize_lang(detectado)

    return declarado, detectado, dec_norm == det_norm if dec_norm and det_norm else False

#----------------------------------------------------------------------------------------------

def pdf_only_image(pdf_path):
    """Checks if the PDF contains only images without text."""
    with fitz.open(pdf_path) as pdf:
        only_images = "Only Images"
        for page in pdf:
            text = page.get_text()
            if text.strip():  # If there is any text, it is not just images
                only_images = "PDF with text"
                break
    return only_images


def lists_not_marked_as_lists(pdf_path):
    """ Identifica listas não marcadas corretamente."""
    doc = fitz.open(pdf_path)
    
    list_pattern = re.compile(r'^(\d+\.|[a-zA-Z]\.|[-*•])\s')
    previous_was_list = False
    
    for page in doc:
        text_dict = page.get_text("dict")
        
        for block in text_dict.get("blocks", []):
            for line in block.get("lines", []):
                for span in line.get("spans", []):
                    text = span.get("text", "").strip()
                    if list_pattern.match(text):
                        if previous_was_list:
                            return False
                        previous_was_list = True
                    else:
                        previous_was_list = False
    
    return True

def allFiguresHaveAltText(pdf_path: str):
    doc, structTree = getStructTree(pdf_path)
    if not doc or not structTree:
        return False
    
    def recursiveBrowse(parent: PdsStructElement):
        elem_type = parent.GetType(True)
        alt_text = parent.GetAlt()
        
        if elem_type.lower() == "figure" and not alt_text:
            return False
        
        for i in range(parent.GetNumChildren()):
            if parent.GetChildType(i) == kPdsStructChildElement:
                if not recursiveBrowse(structTree.GetStructElementFromObject(parent.GetChildObject(i))):
                    return False
        
        return True
    
    root_elem = structTree.GetStructElementFromObject(structTree.GetObject())
    result = recursiveBrowse(root_elem) if root_elem else False
    doc.Close()
    return result


#Links----------------------------------------------------------------------------

def get_links_info(pdf_path):
    doc = fitz.open(pdf_path)
    total_pages = len(doc)

    external_links = []
    internal_links = []
    fake_links = []

    for page_num, page in enumerate(doc):
        links = page.get_links()
        for link in links:
            uri = link.get("uri", "")
            kind = link.get("kind", None)
            dest_page = link.get("page", None)

            if uri.startswith("http://") or uri.startswith("https://"):
                external_links.append(uri)
            elif kind == 1:  # internal link
                if dest_page is not None and 0 <= dest_page < total_pages:
                    internal_links.append((page_num, dest_page))
                else:
                    fake_links.append((page_num, "invalid internal"))
            else:
                if not uri and dest_page is None:
                    fake_links.append((page_num, "no uri or dest"))

    doc.close()
    return external_links, internal_links, fake_links


def check_external_links(links):
    results = []

    for link in links:
        try:
            response = requests.head(link, allow_redirects=True, timeout=5)
            results.append(response.status_code == 200)
        except Exception:
            results.append(False)

    return results

#----------------------------------------------------------------
def check_headers(pdf_path: str) -> bool:
    pdfix = GetPdfix()
    doc = pdfix.OpenDoc(pdf_path, "")
    if not doc:
        return False
    
    structTree = doc.GetStructTree()
    if not structTree:
        return False
    
    header_found = False

    def recursiveBrowse(parent: PdsStructElement):
        nonlocal header_found
        elem_type = parent.GetType(True)

        if elem_type == "Table":
            for i in range(parent.GetNumChildren()):
                child_obj = parent.GetChildObject(i)
                child_elem = structTree.GetStructElementFromObject(child_obj)
                if not child_elem:
                    continue
                if child_elem.GetType(True) == "THead":
                    header_found = True
                recursiveBrowse(child_elem)
        else:
            for i in range(parent.GetNumChildren()):
                if parent.GetChildType(i) == kPdsStructChildElement:
                    recursiveBrowse(structTree.GetStructElementFromObject(parent.GetChildObject(i)))

    root_elem = structTree.GetStructElementFromObject(structTree.GetObject())
    if root_elem:
        recursiveBrowse(root_elem)

    doc.Close()
    return header_found
    
      

# PDF Accessibility Check -----------------------------------------------------

def check_pdf_accessibility(pdf_path):
    """Runs an accessibility evaluation on the PDF."""
    accessibility_report = {
        "Title": None,
        "Language declared": None,
        "Language detected": None,
        "Languages match": False,
        "PDF only image": False,
        "Lists marked as Lists": False,
        "Figures with alt text": False,
        "Links Valid": None, 
        "Table With Headers" : False,
    }

    #print("Evaluating PDF accessibility...\n")

    # Metadata
    title = extract_metadata(pdf_path)
    accessibility_report["Title"] = title
    #accessibility_report["Author"] = author

    # Language detection
    lang_declared, lang_detected, lang_match = compare_languages(pdf_path)
    accessibility_report["Language declared"] = lang_declared
    accessibility_report["Language detected"] = lang_detected
    accessibility_report["Languages match"] = lang_match


    # Check if the PDF is image-only
    accessibility_report["PDF only image"] = pdf_only_image(pdf_path)

    #Check if there is alt text in figures
    accessibility_report["Figures with alt text"] = allFiguresHaveAltText(pdf_path)

    #Lists
    accessibility_report["Lists marked as Lists"] = lists_not_marked_as_lists(pdf_path)

    #Links
    external, internal, fake = get_links_info(pdf_path)
    checked_links = check_external_links(external)

    # Verificar se existe algum link válido
    all_external_valid = all(checked_links) if checked_links else True
    no_fake_links = len(fake) == 0

    link_valid = all_external_valid and no_fake_links

    accessibility_report["Links Valid"] = link_valid

    # Verificar Headers de tabela
    accessibility_report["Table With Headers"] = check_headers(pdf_path)

    return accessibility_report


# Run Analysis -----------------------------------------------------------

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No PDF path provided"}))
        sys.exit(1)

    pdf_file_path = sys.argv[1]

    report = check_pdf_accessibility(pdf_file_path)

    passed = 0
    failed = 0

    for key, value in report.items():
        if key == "Images without alt text":
            if value == 0:
                passed += 1
            else:
                failed += 1
        elif key in ["Language declared", "Language detected"]:
            continue
        else:
            if value in [False, None, "No Title Found", 0, "Only Images"]:
                failed += 1
            else:
                passed += 1

    report["Passed"] = passed
    report["Failed"] = failed

    print(json.dumps(report))
