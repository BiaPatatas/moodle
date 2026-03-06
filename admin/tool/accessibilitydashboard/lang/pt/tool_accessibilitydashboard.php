<?php
/**
 * Portuguese language strings for PDF Accessibility Admin Tool
 */

$string['pluginname'] = 'Painel de Acessibilidade';
$string['dashboard_title'] = 'Painel de Acessibilidade';

// Stats
$string['courses_with_pdfs'] = 'Cursos com PDFs';
$string['total_pdfs'] = 'Total de PDFs';
$string['accessibility_score'] = 'Índice de acessibilidade';
$string['recent_activity'] = 'Atividade recente';

// Filters
$string['filters'] = 'Filtros';
$string['all_departments'] = 'Todos os graus académicos';
$string['all_courses'] = 'Todos os cursos';
$string['last_year'] = 'Último ano';
$string['apply_filters'] = 'Aplicar filtros';

// Departments
$string['departments_performance'] = 'Desempenho por departamento';
$string['department'] = 'Departamento';
$string['courses'] = 'Cursos';
$string['pdfs'] = 'PDFs';
$string['avg_score'] = 'Pontuação média';
$string['actions'] = 'Ações';
$string['view_details'] = 'Ver detalhes';

// Common Errors
$string['common_errors'] = 'Erros mais comuns';
$string['error_type'] = 'Tipo de erro';
$string['occurrences'] = 'Ocorrências';
$string['percentage'] = 'Percentagem';
$string['guidance'] = 'Orientações';

// Objectives
$string['accessibility_objectives'] = 'Objetivos de acessibilidade';
$string['no_objectives'] = 'Ainda não foram definidos objetivos.';
$string['add_objective'] = 'Adicionar objetivo';
$string['edit_objective'] = 'Editar objetivo';
$string['delete_objective'] = 'Eliminar objetivo';
$string['deadline'] = 'Prazo';
$string['edit'] = 'Editar';
$string['delete'] = 'Eliminar';

// Reports
$string['export_report'] = 'Exportar relatório';

// Extra strings for UI in index.php
$string['access_restricted'] = 'Acesso restrito: apenas administradores ou responsáveis de curso podem ver este painel.';
$string['dashboard_subtitle'] = 'Monitorização da acessibilidade da instituição';
$string['export_pdf_report'] = 'Exportar relatório em PDF';

$string['filter_academic_degree'] = 'Grau académico:';
$string['filter_course'] = 'Curso:';
$string['filter_discipline'] = 'Disciplina:';
$string['all_academic_degrees'] = 'Todos os graus académicos';
$string['all_disciplines'] = 'Todas as disciplinas';
$string['clear_filters'] = 'Limpar filtros';

$string['stat_problems_found'] = 'Problemas encontrados';
$string['stat_overall_score'] = 'Pontuação global';

$string['evolution_title'] = 'Evolução da acessibilidade';
$string['evolution_since_last_month'] = 'desde o último mês';

$string['datatable_title'] = 'Dados académicos';
$string['datatable_select_filters'] = 'Selecione filtros para ver os dados das unidades curriculares.';
$string['datatable_no_data'] = 'Não foram encontrados dados para os filtros selecionados.';

$string['column_academic_degree'] = 'Grau académico';
$string['column_course'] = 'Curso';
$string['column_discipline'] = 'Disciplina';
$string['column_pdfs'] = 'PDFs';
$string['column_score'] = 'Pontuação';
$string['column_status'] = 'Estado';

$string['pagination_showing'] = 'A mostrar {$a->from} - {$a->to} de {$a->total} resultados';
$string['pagination_previous'] = 'Anterior';
$string['pagination_next'] = 'Seguinte';
$string['pagination_page_of'] = 'Página {$a->page} de {$a->totalpages}';

$string['best_disciplines_title'] = 'Disciplinas com melhor pontuação';
$string['best_courses_none'] = 'Não foram encontradas unidades curriculares com pontuações de acessibilidade.';

$string['worst_disciplines_title'] = 'Disciplinas com pior pontuação';
$string['worst_courses_none'] = 'Não foram encontradas unidades curriculares com baixas pontuações de acessibilidade.';

$string['most_failed_tests_title'] = 'Testes mais falhados';
$string['no_failed_tests'] = 'Não foram encontrados testes falhados.';
$string['failed_pdfs_of_total'] = '{$a->failed} de {$a->total} PDFs falharam';

$string['chart_accessibility_score'] = 'Pontuação de acessibilidade (%)';
$string['tooltip_accessibility_prefix'] = 'Acessibilidade:';

$string['export_generating'] = 'A gerar PDF...';

// Capabilities
$string['pdfaccessibility:viewdashboard'] = 'Ver painel de acessibilidade de PDF';
