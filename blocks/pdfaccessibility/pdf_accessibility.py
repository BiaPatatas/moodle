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

def is_pdf_properly_tagged(pdf_path):
    """
    Verifica se um PDF está realmente tagged (marcado para acessibilidade)
    
    Retorna:
    - True: PDF está tagged
    - False: PDF não está tagged
    - None: Erro ao verificar
    """
    try:
        # Verificação com pikepdf (mais confiável)
        with pikepdf.Pdf.open(pdf_path) as pdf:
            root = pdf.trailer["/Root"]
            
            # Verificar se existe StructTreeRoot
            struct_tree_root = root.get('/StructTreeRoot', None)
            if not struct_tree_root:
                return False
            
            # Verificar MarkInfo
            mark_info = root.get('/MarkInfo', None)
            if mark_info:
                marked = mark_info.get('/Marked', None)
                if marked is False:  # Explicitamente marcado como False
                    return False
                if marked is True:   # Explicitamente marcado como True
                    return True
            
            # Se tem StructTreeRoot mas MarkInfo não está claro, verificar com PDFixSDK
            return _verify_with_pdfix(pdf_path)
            
    except Exception as e:
        print(f"Erro na verificação pikepdf: {e}")
        return _verify_with_pdfix(pdf_path)

def _verify_with_pdfix(pdf_path):
    """Verificação secundária com PDFixSDK"""
    try:
        pdfix = GetPdfix()
        if not pdfix:
            return None
            
        doc = pdfix.OpenDoc(pdf_path, "")
        if not doc:
            return None
        
        struct_tree = doc.GetStructTree()
        has_struct = struct_tree is not None
        
        if has_struct:
            # Verificar se tem elementos reais
            try:
                root_obj = struct_tree.GetObject()
                if root_obj:
                    root_elem = struct_tree.GetStructElementFromObject(root_obj)
                    if root_elem and root_elem.GetNumChildren() > 0:
                        doc.Close()
                        return True
            except:
                pass
        
        doc.Close()
        return False
        
    except Exception as e:
        return None

#Structure
def getStructTree(pdf_path: str):
    # Primeiro verificar se o PDF está realmente tagged
    if not is_pdf_properly_tagged(pdf_path):
        return None, None
        
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
            
            # Verificar diferentes locais onde a linguagem pode estar
            lang = root.get('/Lang', None)
            
            # Verificar se há StructTreeRoot com linguagem
            struct_tree = root.get('/StructTreeRoot', None)
            if struct_tree:
                struct_lang = struct_tree.get('/Lang', None)
                if struct_lang and not lang:
                    lang = struct_lang
            
            if lang:
                return str(lang).strip()
            return None
    


def detect_language_from_text(path):

        with fitz.open(path) as doc:
            text = "".join(page.get_text() for page in doc)
        
        # Se não há texto, retorna None
        if not text.strip():
            return "Non applicable"
            
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
    doc, structTree = getStructTree(pdf_path)
    if not doc or not structTree:
        return "PDF not tagged"
    
    list_pattern = re.compile(r'^(\d+\.|[a-zA-Z]\.|[-*•])\s')
    visual_list_items = []
    structured_list_count = 0
    
    # Contar todos os itens de lista visuais no texto
    with fitz.open(pdf_path) as fitz_doc:
        for page in fitz_doc:
            text_dict = page.get_text("dict")
            for block in text_dict.get("blocks", []):
                for line in block.get("lines", []):
                    # Concatenar todos os spans da linha para verificar
                    line_text = ""
                    for span in line.get("spans", []):
                        text = span.get("text", "").strip()
                        if text:
                            line_text += text + " "
                    
                    # Verificar se a linha inteira corresponde ao padrão de lista
                    line_text = line_text.strip()
                    if list_pattern.match(line_text):
                        visual_list_items.append(line_text)
                        continue
                    
                    # Também verificar se algum span individual é apenas um marcador
                    for span in line.get("spans", []):
                        text = span.get("text", "").strip()
                        if text in ["•", "▪", "▫", "◦", "‣", "⁃", "➢", "→"] or re.match(r'^\d+\.$', text) or re.match(r'^[a-zA-Z]\.$', text):
                            # Reconstituir o item de lista completo
                            full_item = ""
                            for s in line.get("spans", []):
                                full_item += s.get("text", "").strip() + " "
                            visual_list_items.append(full_item.strip())
                            break
    
    # Contar elementos de lista estruturados no PDF
    def recursiveBrowse(parent: PdsStructElement):
        nonlocal structured_list_count
        elem_type = parent.GetType(True)
        
        # Contar itens de lista estruturados
        if elem_type in ["LI", "ListItem"]:
            structured_list_count += 1
        
        for i in range(parent.GetNumChildren()):
            if parent.GetChildType(i) == kPdsStructChildElement:
                child = structTree.GetStructElementFromObject(parent.GetChildObject(i))
                if child:
                    recursiveBrowse(child)
    
    root_elem = structTree.GetStructElementFromObject(structTree.GetObject())
    if root_elem:
        recursiveBrowse(root_elem)
    
    doc.Close()
    
    # Se não há listas visuais, retorna "Non applicable"
    if not visual_list_items:
        return "Non applicable"
    
    # Para considerar correto, o número de itens estruturados deve ser igual ou maior
    # que o número de itens visuais (algumas listas podem ter estrutura adicional)
    return structured_list_count >= len(visual_list_items)

def allFiguresHaveAltText(pdf_path: str):
    doc, structTree = getStructTree(pdf_path)
    if not doc or not structTree:
        return "PDF not tagged"
    
    figures_found = False
    
    def recursiveBrowse(parent: PdsStructElement):
        nonlocal figures_found
        elem_type = parent.GetType(True)
        alt_text = parent.GetAlt()
        
        if elem_type.lower() == "figure":
            figures_found = True
            if not alt_text:
                return False
        
        for i in range(parent.GetNumChildren()):
            if parent.GetChildType(i) == kPdsStructChildElement:
                if not recursiveBrowse(structTree.GetStructElementFromObject(parent.GetChildObject(i))):
                    return False
        
        return True
    
    root_elem = structTree.GetStructElementFromObject(structTree.GetObject())
    result = recursiveBrowse(root_elem) if root_elem else True
    doc.Close()
    
    # Se não encontrou nenhuma figura, retorna "Non applicable"
    # Se encontrou figuras, retorna True se todas têm alt text, False caso contrário
    return "Non applicable" if not figures_found else result


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

            # Links externos (HTTP, HTTPS, FTP, mailto, etc.)
            if uri and (uri.startswith(("http://", "https://", "ftp://", "mailto:", "tel:"))):
                external_links.append(uri)
            # Links internos
            elif kind == 1 or (dest_page is not None and uri == ""):  # internal link
                if dest_page is not None and 0 <= dest_page < total_pages:
                    internal_links.append((page_num, dest_page))
                else:
                    fake_links.append((page_num, f"invalid internal to page {dest_page}"))
            # Links com URIs não reconhecidos (podem ser válidos mas não HTTP)
            elif uri and not uri.startswith(("http://", "https://", "ftp://", "mailto:", "tel:")):
                # Estes podem ser links válidos com outros protocolos ou caminhos de arquivo
                external_links.append(uri)
            # Links verdadeiramente inválidos
            else:
                if not uri and dest_page is None:
                    fake_links.append((page_num, "no uri or dest"))
                elif uri == "" and dest_page is None:
                    fake_links.append((page_num, "empty uri and no dest"))

    doc.close()
    return external_links, internal_links, fake_links


def check_external_links(links):
    results = []

    for link in links:
        try:
            # Apenas verificar links HTTP/HTTPS
            if link.startswith(("http://", "https://")):
                response = requests.head(link, allow_redirects=True, timeout=5)
                results.append(response.status_code == 200)
            # Para outros tipos de links (mailto, tel, ftp, etc.), assumir como válidos
            # pois não podemos verificá-los facilmente
            elif link.startswith(("mailto:", "tel:", "ftp://")):
                results.append(True)
            # Para links de arquivo ou outros protocolos, assumir como válidos
            else:
                results.append(True)
        except Exception:
            results.append(False)

    return results

#----------------------------------------------------------------
def check_headers(pdf_path: str) -> bool:
    doc, structTree = getStructTree(pdf_path)
    if not doc or not structTree:
        return "PDF not tagged"
    
    tables_with_headers = 0
    tables_without_headers = 0
    total_tables = 0

    def recursiveBrowse(parent: PdsStructElement):
        nonlocal tables_with_headers, tables_without_headers, total_tables
        elem_type = parent.GetType(True)

        if elem_type == "Table":
            total_tables += 1
            has_header = False
            
            # Verificar se esta tabela específica tem cabeçalho
            for i in range(parent.GetNumChildren()):
                child_obj = parent.GetChildObject(i)
                child_elem = structTree.GetStructElementFromObject(child_obj)
                if not child_elem:
                    continue
                if child_elem.GetType(True) == "THead":
                    has_header = True
                    break
            
            if has_header:
                tables_with_headers += 1
            else:
                tables_without_headers += 1
                
            # Continuar a recursão para elementos filhos
            for i in range(parent.GetNumChildren()):
                child_obj = parent.GetChildObject(i)
                child_elem = structTree.GetStructElementFromObject(child_obj)
                if child_elem:
                    recursiveBrowse(child_elem)
        else:
            for i in range(parent.GetNumChildren()):
                if parent.GetChildType(i) == kPdsStructChildElement:
                    recursiveBrowse(structTree.GetStructElementFromObject(parent.GetChildObject(i)))

    root_elem = structTree.GetStructElementFromObject(structTree.GetObject())
    if root_elem:
        recursiveBrowse(root_elem)

    doc.Close()

    # Se não há tabelas, retorna "Non applicable"
    if total_tables == 0:
        return "Non applicable"
    
    # Para ser considerado correto, TODAS as tabelas devem ter cabeçalhos
    return tables_without_headers == 0
    
      

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

    # Verificar se o PDF está tagged (para uso interno, não no resultado final)
    is_tagged = is_pdf_properly_tagged(pdf_path)

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
    
    # Se não há nenhum tipo de link, retorna None
    if not external and not internal and not fake:
        accessibility_report["Links Valid"] = "Non applicable"
    else:
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
    ne = 0

    for key, value in report.items():
        # Pular verificações que são apenas informativas
        if key in ["Language declared", "Language detected"]:
            continue
            
        # Tratar "PDF not tagged" como falha
        if value == "PDF not tagged":
            failed += 1
        elif value == "Non applicable":
            ne += 1
        elif value in [False, None, "No Title Found", 0, "Only Images"]:
            failed += 1
        else:
            passed += 1

    # report["Passed"] = passed
    # report["Failed"] = failed
    # report["Non applicable"] = ne

    print(json.dumps(report))