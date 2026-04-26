import re
import PyPDF2
from langdetect import detect
import pdfplumber
import fitz  # PyMuPDF
import pikepdf
from pdfixsdk.Pdfix import *
import requests

try:
    import pytesseract
    from PIL import Image
    OCR_AVAILABLE = True
except ImportError:
    OCR_AVAILABLE = False

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
    # Normaliza tags como "pt", "pt-PT" ou "pt_PT" para apenas o código de língua
    # Ex.: "pt_PT" -> "pt", "en-US" -> "en"
    cleaned = tag.lower().replace('_', '-')
    return cleaned.split('-')[0]


def compare_languages(pdf_path):
    declarado = get_pdf_declared_language(pdf_path)
    detectado = detect_language_from_text(pdf_path)

    dec_norm = normalize_lang(declarado)
    det_norm = normalize_lang(detectado)

    return declarado, detectado, dec_norm == det_norm if dec_norm and det_norm else False

#----------------------------------------------------------------------------------------------

def pdf_only_image(pdf_path):
    """Classifica o tipo de conteúdo em termos de OCR (técnica PDF7).

    Retorna:
      - "PDF with text" → existe camada de texto extraível em pelo menos
        uma página (PDF nativo ou já passou por OCR).
      - "Scanned PDF without OCR" → não existe texto extraível, mas o OCR
        detecta uma quantidade significativa de texto nas imagens
        (documento digitalizado sem camada de texto).
      - "Only Images" → não há texto extraível e o OCR não encontra texto
        suficiente; tipicamente imagens/fotografias, não um documento
        digitalizado de texto.
    """

    # Primeiro, procurar texto embutido (camada de texto/OCR existente)
    with fitz.open(pdf_path) as pdf:
        has_embedded_text = False
        for page in pdf:
            text = page.get_text().strip()
            if text:
                has_embedded_text = True
                break

    if has_embedded_text:
        return "PDF with text"

    # Se não há texto embutido, usar OCR apenas para distinguir
    # documento digitalizado de imagem/fotografia.
    if not OCR_AVAILABLE:
        # Sem OCR não conseguimos distinguir; considerar apenas imagens.
        return "Only Images"

    ocr_char_count = 0
    try:
        with fitz.open(pdf_path) as pdf:
            for page in pdf:
                pix = page.get_pixmap()
                mode = "RGBA" if pix.alpha else "RGB"
                img = Image.frombytes(mode, [pix.width, pix.height], pix.samples)
                ocr_text = pytesseract.image_to_string(img)
                # Contar apenas caracteres alfanuméricos para reduzir ruído
                cleaned = "".join(ch for ch in ocr_text if ch.isalnum())
                ocr_char_count += len(cleaned)
    except Exception:
        # Se OCR falhar por completo, comportar-se como apenas imagens
        return "Only Images"

    # Limite heurístico: se o OCR encontra texto suficiente, assumimos
    # que é um documento digitalizado de texto sem camada de texto.
    # Reduzido para 30 caracteres para apanhar páginas com pouco texto.
    if ocr_char_count >= 30:
        return "Scanned PDF without OCR"

    return "Only Images"


def lists_not_marked_as_lists(pdf_path):
    """Identifica listas não marcadas corretamente.

    Retorna apenas um resultado global:
      - True / False / "PDF not tagged" / "Non applicable"
    """
    doc, structTree = getStructTree(pdf_path)
    if not doc or not structTree:
        return "PDF not tagged"

    marker_pattern = re.compile(
        r'^(?:[-*•▪▫◦‣⁃➢→]\s+|(?:\(?\d{1,3}[\.)]|[a-zA-Z][\.)])\s+)'
    )
    section_pattern = re.compile(r'^\d+(?:\.\d+){1,}\s')
    reference_pattern = re.compile(r'^\[\d+\]')
    caption_pattern = re.compile(r'^(?:fig\.|figure|table)\s*\d+', re.IGNORECASE)

    visual_candidates = []
    structured_list_count = 0

    def _normalize_line_text(line):
        chunks = []
        for span in line.get("spans", []):
            text = span.get("text", "").strip()
            if text:
                chunks.append(text)
        return " ".join(chunks).strip()

    def _first_non_empty_span_text(line):
        for span in line.get("spans", []):
            text = span.get("text", "").strip()
            if text:
                return text
        return ""

    def _is_visual_list_candidate(line_text, first_span_text):
        if not line_text:
            return False

        # Filtrar padrões comuns de falsos positivos em papers.
        if reference_pattern.match(line_text):
            return False
        if section_pattern.match(line_text):
            return False
        if caption_pattern.match(line_text):
            return False

        # Limite para evitar linhas muito longas de parágrafo/referência.
        if len(line_text) > 180:
            return False

        # O marcador tem de estar no início visual da linha.
        if marker_pattern.match(line_text):
            return True

        # Alternativa: primeiro span é bullet isolado e há texto depois.
        if first_span_text in ["•", "▪", "▫", "◦", "‣", "⁃", "➢", "→"]:
            return True

        return False

    def _has_list_context(candidate, page_candidates):
        """Exige pelo menos um vizinho com y/x próximos na mesma página."""
        bbox = candidate.get("bbox")
        if not bbox:
            return False

        x0, y0 = bbox[0], bbox[1]
        for other in page_candidates:
            if other is candidate:
                continue
            obox = other.get("bbox")
            if not obox:
                continue

            ox0, oy0 = obox[0], obox[1]
            if abs(y0 - oy0) <= 45 and abs(x0 - ox0) <= 30:
                return True
        return False

    # Contar todos os itens de lista visuais no texto
    with fitz.open(pdf_path) as fitz_doc:
        for page in fitz_doc:
            text_dict = page.get_text("dict")
            for block in text_dict.get("blocks", []):
                for line in block.get("lines", []):
                    line_text = _normalize_line_text(line)
                    first_span = _first_non_empty_span_text(line)

                    if _is_visual_list_candidate(line_text, first_span):
                        visual_candidates.append({
                            "page": page.number + 1,
                            "text": line_text,
                            "bbox": line.get("bbox", None),
                        })

    visual_list_items = []
    candidates_by_page = {}
    for item in visual_candidates:
        candidates_by_page.setdefault(item["page"], []).append(item)

    for _, page_candidates in candidates_by_page.items():
        for item in page_candidates:
            if _has_list_context(item, page_candidates):
                visual_list_items.append(item)

    # Contar elementos de lista estruturados no PDF
    def recursiveBrowse(parent: PdsStructElement):
        nonlocal structured_list_count
        elem_type = parent.GetType(True)

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

    if not visual_list_items:
        return "Non applicable"

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
                # Guardar também o número da página (0-based aqui)
                external_links.append((page_num, uri))
            # Links internos
            elif kind == 1 or (dest_page is not None and uri == ""):  # internal link
                if dest_page is not None and 0 <= dest_page < total_pages:
                    internal_links.append((page_num, dest_page))
                else:
                    fake_links.append((page_num, f"invalid internal to page {dest_page}"))
            # Links com URIs não reconhecidos (podem ser válidos mas não HTTP)
            elif uri and not uri.startswith(("http://", "https://", "ftp://", "mailto:", "tel:")):
                # Estes podem ser links válidos com outros protocolos ou caminhos de arquivo
                external_links.append((page_num, uri))
            # Links verdadeiramente inválidos
            else:
                if not uri and dest_page is None:
                    fake_links.append((page_num, "no uri or dest"))
                elif uri == "" and dest_page is None:
                    fake_links.append((page_num, "empty uri and no dest"))

    doc.close()
    return external_links, internal_links, fake_links


def check_external_links(links):
    """Verifica links externos.

    links: lista de tuplos (page_num, uri) com page_num 0-based.
    Retorna:
      - lista de resultados booleanos (True/False) por link
      - lista de links quebrados [(page_num, uri, motivo), ...]
    """
    results = []
    broken_links = []

    for page_num, link in links:
        try:
            # Apenas verificar links HTTP/HTTPS
            if link.startswith(("http://", "https://")):
                # Usar GET em vez de HEAD porque muitos servidores
                # não suportam bem HEAD mas funcionam no browser.
                response = requests.get(link, allow_redirects=True, timeout=10)
                # Considerar válido qualquer código 2xx ou 3xx
                is_ok = 200 <= response.status_code < 400
                results.append(is_ok)
                if not is_ok:
                    broken_links.append((page_num, link, f"HTTP status {response.status_code}"))
            # Para outros tipos de links (mailto, tel, ftp, etc.), assumir como válidos
            # pois não podemos verificá-los facilmente
            elif link.startswith(("mailto:", "tel:", "ftp://")):
                results.append(True)
            # Para links de arquivo ou outros protocolos, assumir como válidos
            else:
                results.append(True)
        except Exception as e:
            results.append(False)
            broken_links.append((page_num, link, str(e)))

    return results, broken_links

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
                
                c_type = child_elem.GetType(True)
                
                # 1. Verifica se tem o bloco de agrupamento THead (PDFs normais)
                if c_type == "THead":
                    has_header = True
                    break
                
                # 2. NOVIDADE: Se for uma linha (TR), verifica se tem células TH (PDFs do LaTeX)
                elif c_type == "TR":
                    for j in range(child_elem.GetNumChildren()):
                        cell_obj = child_elem.GetChildObject(j)
                        cell_elem = structTree.GetStructElementFromObject(cell_obj)
                        if cell_elem and cell_elem.GetType(True) == "TH":
                            has_header = True
                            break
                
                # Se encontrou o cabeçalho na verificação da linha, sai do loop principal da Tabela
                if has_header:
                    break
            
            if has_header:
                tables_with_headers += 1
            else:
                tables_without_headers += 1
                
            # Continuar a recursão para elementos filhos (caso existam tabelas aninhadas)
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

    if total_tables == 0:
        return "Non applicable"
    
    return tables_without_headers == 0

# PDF Accessibility Check -----------------------------------------------------

def check_pdf_accessibility(pdf_path):
    """Runs an accessibility evaluation on the PDF."""
    accessibility_report = {
        "Title": None,
        "Language declared": None,
        "Language detected": None,
        "Languages match": False,
        "PDF OCR status": False,
        "Lists marked as Lists": False,
        "Figures with alt text": False,
        "Links Valid": None,
        "Links Error Pages": None,
        "Links Error Detail": None,
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
    accessibility_report["PDF OCR status"] = pdf_only_image(pdf_path)

    #Check if there is alt text in figures
    accessibility_report["Figures with alt text"] = allFiguresHaveAltText(pdf_path)

    #Lists (apenas resultado global)
    accessibility_report["Lists marked as Lists"] = lists_not_marked_as_lists(pdf_path)

    #Links
    external, internal, fake = get_links_info(pdf_path)
    
    # Se não há nenhum tipo de link, retorna None
    if not external and not internal and not fake:
        accessibility_report["Links Valid"] = "Non applicable"
        accessibility_report["Links Error Pages"] = "Non applicable"
        accessibility_report["Links Error Detail"] = "Non applicable"
    else:
        checked_links, broken_links = check_external_links(external)

        # Verificar se existe algum link válido
        all_external_valid = all(checked_links) if checked_links else True
        no_fake_links = len(fake) == 0

        link_valid = all_external_valid and no_fake_links

        accessibility_report["Links Valid"] = link_valid

        # Determinar em que páginas existem erros de links (1-based para o utilizador)
        error_pages = set()
        error_details = []
        # Links internos/fake (ex.: para página inexistente)
        for page_num, reason in fake:
            page_display = page_num + 1
            error_pages.add(page_display)
            error_details.append({
                "page": page_display,
                "type": "internal",
                "info": reason,
            })
        # Links externos quebrados
        for page_num, uri, msg in broken_links:
            page_display = page_num + 1
            error_pages.add(page_display)
            error_details.append({
                "page": page_display,
                "type": "external",
                "url": uri,
                "error": msg,
            })

        accessibility_report["Links Error Pages"] = sorted(error_pages) if error_pages else []
        accessibility_report["Links Error Detail"] = error_details

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
        if key in [
            "Language declared",
            "Language detected",
            "Links Error Pages",
            "Links Error Detail",
        ]:
            continue
            
        # Tratar "PDF not tagged" como falha
        if value == "PDF not tagged":
            failed += 1
        # Tratar casos "Non applicable"
        elif value == "Non applicable":
            ne += 1
        # Tratamento específico para o teste de OCR em PDFs digitalizados (técnica PDF7)
        elif key == "PDF OCR status":
            # Documento de imagens onde o OCR não encontra texto suficiente:
            # considerar que o teste não se aplica (por ex., apenas fotografia).
            if value == "Only Images":
                ne += 1
            # Documento digitalizado de texto sem camada de texto/OCR:
            # falha do PDF7.
            elif value == "Scanned PDF without OCR":
                failed += 1
            # "PDF with text" é tratado como sucesso (não entra aqui).
        elif value in [False, None, "No Title Found", 0]:
            failed += 1
        else:
            passed += 1

    report["Passed"] = passed
    report["Failed"] = failed
    report["Non applicable"] = ne

    print(json.dumps(report))