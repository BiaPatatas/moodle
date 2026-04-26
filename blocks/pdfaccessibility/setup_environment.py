import sys
import subprocess
import platform
import shutil

REQUIRED_PACKAGES = [
    "PyPDF2",
    "langdetect",
    "pdfplumber",
    "pymupdf",   # fornece o módulo 'fitz'
    "pikepdf",
    "requests",
    "pytesseract",
    "Pillow",
]

# pdfixsdk é tratado em separado porque normalmente depende de instalação do SDK
PDFIX_PACKAGE = "pdfixsdk"


def run_pip_install(package: str) -> bool:
    """Instala um package via pip, devolve True/False consoante sucesso."""
    print(f"\n[INFO] A instalar pacote Python: {package} ...")
    try:
        subprocess.check_call([
            sys.executable,
            "-m",
            "pip",
            "install",
            package,
        ])
        print(f"[OK] Pacote '{package}' instalado com sucesso.")
        return True
    except subprocess.CalledProcessError as e:
        print(f"[ERRO] Falha ao instalar '{package}': {e}")
        return False


def check_python_version() -> None:
    """Verifica se a versão de Python é razoável (3.8+ recomendado)."""
    major, minor = sys.version_info[:2]
    print(f"[INFO] Python detectado: {major}.{minor}")
    if major < 3 or (major == 3 and minor < 8):
        print("[AVISO] Recomenda-se Python 3.8 ou superior para este projeto.")


def check_tesseract() -> None:
    """Verifica se o executável tesseract está disponível no PATH."""
    print("\n[CHECK] A verificar Tesseract OCR...")
    path = shutil.which("tesseract")
    if path:
        print(f"[OK] Tesseract encontrado em: {path}")
    else:
        print("[AVISO] Tesseract *não* foi encontrado no PATH.")
        print("        - Instalação recomendada (Windows): Tesseract-OCR para Windows")
        print("        - Depois adicionar a pasta de instalação ao PATH (ex.: C\\\Program Files\\\Tesseract-OCR)")


def check_pdfixsdk() -> None:
    """Tenta importar pdfixsdk e avisa se não estiver disponível."""
    print("\n[CHECK] A verificar PDFix SDK (pdfixsdk)...")
    try:
        __import__(PDFIX_PACKAGE)
        print("[OK] Módulo 'pdfixsdk' importado com sucesso.")
    except ImportError:
        print("[AVISO] Não foi possível importar 'pdfixsdk'.")
        print("        - É necessário instalar o PDFix SDK para Windows e respetivas bindings Python.")
        print("        - Ver documentação oficial do PDFix para instalação e configuração.")


def main() -> None:
    print("============================================")
    print("  Setup de ambiente para bloco PDF Accessibility")
    print("============================================\n")

    # 1) Verificar SO
    system = platform.system()
    print(f"[INFO] Sistema operativo: {system}")
    if system != "Windows":
        print("[AVISO] Este script foi pensado para Windows; noutros SO pode falhar.")

    # 2) Verificar versão de Python
    check_python_version()

    # 3) Instalar pacotes obrigatórios
    print("\n[PASSO] Instalar bibliotecas Python necessárias...")
    ok_all = True
    for pkg in REQUIRED_PACKAGES:
        if not run_pip_install(pkg):
            ok_all = False

    # 4) Tentar instalar pdfixsdk via pip (pode falhar se não houver SDK/licença)
    print("\n[PASSO] (Opcional) Tentar instalar 'pdfixsdk' via pip...")
    run_pip_install(PDFIX_PACKAGE)

    # 5) Checks adicionais
    check_tesseract()
    check_pdfixsdk()

    print("\n============================================")
    print("Resumo:")
    if ok_all:
        print("  - Bibliotecas principais instaladas (PyPDF2, langdetect, pdfplumber, pymupdf, pikepdf, requests, pytesseract, Pillow).")
    else:
        print("  - Ocorreram erros na instalação de algumas bibliotecas; rever mensagens acima.")
    print("  - Verificar se Tesseract e PDFix SDK estão devidamente instalados.")
    print("============================================\n")

if __name__ == "__main__":
    main()
