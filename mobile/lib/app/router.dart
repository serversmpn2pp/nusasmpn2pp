import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/auth/presentation/ganti_kata_sandi_view.dart';
import 'package:nusa/features/auth/presentation/login_view.dart';
import 'package:nusa/features/auth/presentation/startup_view.dart';
import 'package:nusa/features/asset_unit/presentation/asset_unit_view.dart';
import 'package:nusa/features/academic_year/presentation/academic_year_view.dart';
import 'package:nusa/features/class_promotion/presentation/class_promotion_view.dart';
import 'package:nusa/features/class_assessment/presentation/class_assessment_detail_view.dart';
import 'package:nusa/features/class_assessment/presentation/class_assessment_correction_view.dart';
import 'package:nusa/features/class_assessment/presentation/class_assessment_form_view.dart';
import 'package:nusa/features/class_assessment/presentation/class_assessment_list_view.dart';
import 'package:nusa/features/class_assessment/presentation/class_assessment_monitoring_view.dart';
import 'package:nusa/features/class_assessment/presentation/class_assessment_questions_view.dart';
import 'package:nusa/features/class_assessment/presentation/class_assessment_results_view.dart';
import 'package:nusa/features/cbt_center/domain/cbt_center.dart';
import 'package:nusa/features/cbt_center/presentation/cbt_center_view.dart';
import 'package:nusa/features/central_exam_execution/presentation/central_exam_execution_detail_view.dart';
import 'package:nusa/features/central_exam_execution/presentation/central_exam_execution_list_view.dart';
import 'package:nusa/features/central_exam_correction/presentation/central_exam_correction_view.dart';
import 'package:nusa/features/central_exam_results/presentation/central_exam_results_detail_view.dart';
import 'package:nusa/features/central_exam_results/presentation/central_exam_results_list_view.dart';
import 'package:nusa/features/central_exam_preparation/domain/central_exam_preparation.dart';
import 'package:nusa/features/central_exam_preparation/presentation/central_exam_distribution_detail_view.dart';
import 'package:nusa/features/central_exam_preparation/presentation/central_exam_event_form_view.dart';
import 'package:nusa/features/central_exam_preparation/presentation/central_exam_preparation_detail_view.dart';
import 'package:nusa/features/central_exam_preparation/presentation/central_exam_preparation_list_view.dart';
import 'package:nusa/features/employee/presentation/employee_detail_view.dart';
import 'package:nusa/features/employee/presentation/employee_list_view.dart';
import 'package:nusa/features/employee_card/presentation/employee_card_view.dart';
import 'package:nusa/features/employee_attendance_settings/presentation/employee_attendance_settings_view.dart';
import 'package:nusa/features/employee_attendance_recap/presentation/employee_attendance_recap_view.dart';
import 'package:nusa/features/employee_attendance_report/presentation/employee_attendance_report_view.dart';
import 'package:nusa/features/employee_scan_status/presentation/employee_scan_status_view.dart';
import 'package:nusa/features/exam_supervision/presentation/exam_supervision_detail_view.dart';
import 'package:nusa/features/exam_attendance/presentation/exam_attendance_detail_view.dart';
import 'package:nusa/features/exam_attendance/presentation/exam_attendance_list_view.dart';
import 'package:nusa/features/facility_dashboard/presentation/facility_dashboard_view.dart';
import 'package:nusa/features/early_warning_setting/presentation/early_warning_setting_view.dart';
import 'package:nusa/features/employee_account/presentation/employee_account_detail_view.dart';
import 'package:nusa/features/employee_account/presentation/employee_account_list_view.dart';
import 'package:nusa/features/grade_weight_scheme/presentation/grade_weight_scheme_view.dart';
import 'package:nusa/features/grade_component/presentation/grade_component_view.dart';
import 'package:nusa/features/grade_entry/presentation/grade_entry_view.dart';
import 'package:nusa/features/grade_recap/presentation/grade_recap_view.dart';
import 'package:nusa/features/goods_receipt/presentation/goods_receipt_view.dart';
import 'package:nusa/features/guardian_assignment/presentation/guardian_assignment_create_view.dart';
import 'package:nusa/features/guardian_assignment/presentation/guardian_assignment_list_view.dart';
import 'package:nusa/features/home/presentation/home_view.dart';
import 'package:nusa/features/identity_photo/presentation/identity_photo_view.dart';
import 'package:nusa/features/inventory_acquisition_source/presentation/inventory_acquisition_source_view.dart';
import 'package:nusa/features/inventory_category/presentation/inventory_category_view.dart';
import 'package:nusa/features/inventory_goods/presentation/inventory_goods_view.dart';
import 'package:nusa/features/inventory_location/presentation/inventory_location_view.dart';
import 'package:nusa/features/inventory_label/presentation/inventory_label_view.dart';
import 'package:nusa/features/inventory_settings/presentation/inventory_settings_view.dart';
import 'package:nusa/features/inventory_unit/presentation/inventory_unit_view.dart';
import 'package:nusa/features/incident_reporting/presentation/incident_reporting_view.dart';
import 'package:nusa/features/lesson_period/presentation/lesson_period_view.dart';
import 'package:nusa/features/learning_survey/presentation/learning_survey_view.dart';
import 'package:nusa/features/late_point_setting/presentation/late_point_setting_view.dart';
import 'package:nusa/features/login_activity/presentation/login_activity_list_view.dart';
import 'package:nusa/features/login_activity/presentation/login_attempt_detail_view.dart';
import 'package:nusa/features/menu/presentation/menu_group_view.dart';
import 'package:nusa/features/my_teaching_schedule/presentation/my_teaching_schedule_view.dart';
import 'package:nusa/features/my_grades/presentation/my_grades_view.dart';
import 'package:nusa/features/my_guardian_students/presentation/my_guardian_student_detail_view.dart';
import 'package:nusa/features/my_guardian_students/presentation/my_guardian_student_list_view.dart';
import 'package:nusa/features/parent_account/presentation/parent_account_detail_view.dart';
import 'package:nusa/features/parent_account/presentation/parent_account_list_view.dart';
import 'package:nusa/features/point_sanction_rule/presentation/point_sanction_rule_view.dart';
import 'package:nusa/features/point_reduction/presentation/point_reduction_view.dart';
import 'package:nusa/features/private_worship_scan/presentation/private_worship_scan_view.dart';
import 'package:nusa/features/private_confirmation/presentation/private_confirmation_detail_view.dart';
import 'package:nusa/features/private_confirmation/presentation/private_confirmation_list_view.dart';
import 'package:nusa/features/question_bank/presentation/question_bank_detail_view.dart';
import 'package:nusa/features/question_bank/presentation/question_bank_form_view.dart';
import 'package:nusa/features/question_bank/presentation/question_bank_list_view.dart';
import 'package:nusa/features/question_package/presentation/question_package_detail_view.dart';
import 'package:nusa/features/question_package/presentation/question_package_list_view.dart';
import 'package:nusa/features/report_verification/presentation/report_verification_detail_view.dart';
import 'package:nusa/features/report_verification/presentation/report_verification_list_view.dart';
import 'package:nusa/features/role_access/presentation/role_access_detail_view.dart';
import 'package:nusa/features/role_access/presentation/role_access_list_view.dart';
import 'package:nusa/features/school_class/presentation/school_class_detail_view.dart';
import 'package:nusa/features/school_class/presentation/school_class_list_view.dart';
import 'package:nusa/features/student/presentation/student_detail_view.dart';
import 'package:nusa/features/student/presentation/student_list_view.dart';
import 'package:nusa/features/student_account/presentation/student_account_detail_view.dart';
import 'package:nusa/features/student_account/presentation/student_account_list_view.dart';
import 'package:nusa/features/student_assistance/presentation/student_assistance_create_view.dart';
import 'package:nusa/features/student_assistance/presentation/student_assistance_detail_view.dart';
import 'package:nusa/features/student_assistance/presentation/student_assistance_list_view.dart';
import 'package:nusa/features/student_early_warning/presentation/student_early_warning_detail_view.dart';
import 'package:nusa/features/student_early_warning/presentation/student_early_warning_list_view.dart';
import 'package:nusa/features/student_exam/presentation/student_exam_view.dart';
import 'package:nusa/features/student_point_recap/presentation/student_point_recap_detail_view.dart';
import 'package:nusa/features/student_point_recap/presentation/student_point_recap_list_view.dart';
import 'package:nusa/features/student_sanction/presentation/student_sanction_detail_view.dart';
import 'package:nusa/features/student_sanction/presentation/student_sanction_list_view.dart';
import 'package:nusa/features/student_attendance_settings/presentation/student_attendance_settings_view.dart';
import 'package:nusa/features/student_attendance_recap/presentation/student_attendance_recap_view.dart';
import 'package:nusa/features/student_attendance_report/presentation/student_attendance_report_view.dart';
import 'package:nusa/features/student_scan_status/presentation/student_scan_status_view.dart';
import 'package:nusa/features/student_card/presentation/student_card_view.dart';
import 'package:nusa/features/student_guidance_category/presentation/student_guidance_category_view.dart';
import 'package:nusa/features/student_placement/presentation/student_placement_view.dart';
import 'package:nusa/features/student_report/presentation/student_report_detail_view.dart';
import 'package:nusa/features/student_report/presentation/student_report_list_view.dart';
import 'package:nusa/features/student_report/domain/student_report.dart';
import 'package:nusa/features/student_violation_type/presentation/student_violation_type_view.dart';
import 'package:nusa/features/stock/presentation/stock_balance_view.dart';
import 'package:nusa/features/stock/presentation/stock_movement_view.dart';
import 'package:nusa/features/subject/presentation/subject_view.dart';
import 'package:nusa/features/survey_statement/presentation/survey_statement_view.dart';
import 'package:nusa/features/survey_monitoring/presentation/survey_monitoring_detail_view.dart';
import 'package:nusa/features/survey_monitoring/presentation/survey_monitoring_view.dart';
import 'package:nusa/features/teaching_assignment/presentation/teaching_assignment_view.dart';
import 'package:nusa/features/teaching_document/presentation/teaching_document_detail_view.dart';
import 'package:nusa/features/teaching_document/presentation/teaching_document_view.dart';
import 'package:nusa/features/teaching_document_review/presentation/teaching_document_review_detail_view.dart';
import 'package:nusa/features/teaching_document_review/presentation/teaching_document_review_view.dart';
import 'package:nusa/features/teaching_document_review/presentation/teaching_document_teacher_detail_view.dart';
import 'package:nusa/features/teaching_document_type/presentation/teaching_document_type_view.dart';
import 'package:nusa/features/teacher_duty/presentation/my_teacher_duty_view.dart';
import 'package:nusa/features/teacher_duty/presentation/teacher_duty_schedule_view.dart';
import 'package:nusa/features/worship_activity/presentation/worship_activity_view.dart';
import 'package:nusa/features/worship_absence_settings/presentation/worship_absence_settings_view.dart';
import 'package:nusa/features/worship_monthly_summary/presentation/worship_monthly_summary_view.dart';
import 'package:nusa/features/worship_schedule/presentation/worship_schedule_view.dart';
import 'package:nusa/features/worship_scan/presentation/worship_scan_view.dart';
import 'package:nusa/features/violation_process_deadline/presentation/violation_process_deadline_view.dart';
import 'package:nusa/features/worship_recap/domain/worship_recap.dart';
import 'package:nusa/features/worship_recap/presentation/worship_correction_view.dart';
import 'package:nusa/features/worship_recap/presentation/worship_recap_view.dart';

abstract final class AppRoutes {
  static const startup = '/startup';
  static const login = '/login';
  static const gantiKataSandi = '/ganti-kata-sandi';
  static const home = '/beranda';
  static const cbtCenter = '/pusat-cbt';
  static const facilityDashboard = '/dashboard-sarpras';
  static const inventoryGoods = '/barang';
  static const inventoryCategories = '/kategori-barang';
  static const inventoryLocations = '/lokasi-barang';
  static const inventoryAcquisitionSources = '/sumber-perolehan';
  static const inventorySettings = '/pengaturan-inventaris';
  static const inventoryUnits = '/satuan-barang';
  static const assetUnits = '/unit-aset';
  static const inventoryLabels = '/label-inventaris';
  static const goodsReceipts = '/barang-datang';
  static const stockBalances = '/saldo-stok';
  static const stockMovements = '/mutasi-stok';
  static const centralExamExecution = '/pelaksanaan-ujian-terpusat';
  static const centralExamExecutionDetail = '/pelaksanaan-ujian-terpusat/:id';
  static const centralExamPreparation = '/ujian-terpusat';
  static const centralExamPreparationCreate = '/ujian-terpusat/tambah';
  static const centralExamPreparationEdit = '/ujian-terpusat/:id/ubah';
  static const centralExamDistributionDetail =
      '/ujian-terpusat/:eventId/pembagian/:groupId';
  static const centralExamPreparationDetail = '/ujian-terpusat/:id';
  static const centralExamResults = '/hasil-ujian-terpusat';
  static const centralExamResultsDetail = '/hasil-ujian-terpusat/:id';
  static const centralExamCorrection =
      '/hasil-ujian-terpusat/:eventId/jadwal/:scheduleId/koreksi-uraian';
  static const myExamSupervision = '/tugas-pengawas-ujian';
  static const myExamSupervisionDetail = '/tugas-pengawas-ujian/:id';
  static const examAttendance = '/presensi-ujian';
  static const examAttendanceDetail = '/presensi-ujian/:id';
  static const myExams = '/ujian-saya';
  static const studentExam = '/ujian-saya/:id';
  static const questionBank = '/bank-soal';
  static const questionBankCreate = '/bank-soal/tambah';
  static const questionBankDetail = '/bank-soal/:id';
  static const questionBankEdit = '/bank-soal/:id/ubah';
  static const questionPackages = '/paket-soal';
  static const questionPackageDetail = '/paket-soal/:id';
  static const classAssessments = '/asesmen-kelas';
  static const classAssessmentCreate = '/asesmen-kelas/tambah';
  static const classAssessmentDetail = '/asesmen-kelas/:id';
  static const classAssessmentEdit = '/asesmen-kelas/:id/ubah';
  static const classAssessmentQuestions = '/asesmen-kelas/:id/soal';
  static const classAssessmentMonitoring = '/asesmen-kelas/:id/monitoring';
  static const classAssessmentResults = '/asesmen-kelas/:id/hasil';
  static const classAssessmentCorrection = '/asesmen-kelas/:id/koreksi-uraian';
  static const incidentReporting = '/laporkan-kejadian';
  static const studentReports = '/daftar-laporan-siswa';
  static const studentReportDetail = '/daftar-laporan-siswa/:id';
  static const guardianStudentReports = '/laporan-siswa-wali';
  static const guardianStudentReportDetail = '/laporan-siswa-wali/:id';
  static const reportVerification = '/pemeriksaan-pengesahan';
  static const reportVerificationDetail = '/pemeriksaan-pengesahan/:id';
  static const studentAssistance = '/pendampingan-siswa';
  static const studentEarlyWarnings = '/peringatan-dini-siswa';
  static const studentPointRecaps = '/rekap-poin-siswa';
  static const pointReductions = '/pengurangan-poin-siswa';
  static const guardianAssignments = '/penugasan-guru-wali';
  static const myGuardianStudents = '/siswa-wali-saya';
  static const studentSanctions = '/pelaksanaan-sanksi-siswa';
  static const employees = '/pegawai';
  static const employeeDetail = '/pegawai/:id';
  static const employeeAccounts = '/akun-pegawai';
  static const employeeAccountDetail = '/akun-pegawai/:id';
  static const students = '/siswa';
  static const studentDetail = '/siswa/:id';
  static const studentAccounts = '/akun-siswa';
  static const studentAccountDetail = '/akun-siswa/:id';
  static const parentAccounts = '/akun-orang-tua';
  static const parentAccountDetail = '/akun-orang-tua/:id';
  static const loginActivities = '/aktivitas-login';
  static const loginActivityDetail = '/aktivitas-login/:id';
  static const classes = '/kelas';
  static const academicYears = '/tahun-pelajaran';
  static const classPromotion = '/kenaikan-kelas';
  static const studentPlacement = '/penempatan-siswa';
  static const identityPhotos = '/foto-identitas';
  static const employeeCards = '/kartu-pegawai';
  static const studentCards = '/kartu-pelajar';
  static const studentAttendanceSettings = '/pengaturan-presensi-siswa';
  static const employeeAttendanceSettings = '/pengaturan-presensi-pegawai';
  static const employeeAttendanceRecap = '/rekap-presensi-pegawai';
  static const employeeAttendanceReport = '/laporan-presensi-pegawai';
  static const employeeScanStatus = '/status-scan-presensi-pegawai';
  static const studentScanStatus = '/status-scan-presensi-siswa';
  static const studentAttendanceRecap = '/rekap-presensi-siswa';
  static const studentAttendanceReport = '/laporan-presensi-siswa';
  static const teacherDutySchedules = '/jadwal-guru-piket';
  static const myTeacherDuty = '/piket-saya';
  static const classDetail = '/kelas/:id';
  static const lessonPeriods = '/jam-pelajaran';
  static const subjects = '/mata-pelajaran';
  static const teachingAssignments = '/guru-mata-pelajaran';
  static const myTeachingSchedule = '/jadwal-mengajar-saya';
  static const gradeWeightSchemes = '/skema-bobot-nilai';
  static const gradeComponents = '/komponen-nilai';
  static const gradeEntry = '/input-nilai';
  static const gradeRecap = '/rekap-nilai-rapor';
  static const myGrades = '/nilai-saya';
  static const learningSurvey = '/survei-pembelajaran/:assignmentId/:semester';
  static const surveyStatements = '/pernyataan-survei';
  static const surveyMonitoring = '/monitoring-survei';
  static const teachingDocuments = '/perangkat-ajar-saya';
  static const teachingDocumentReviews = '/pemeriksaan-perangkat-ajar';
  static const teachingDocumentTypes = '/jenis-perangkat-ajar';
  static const worshipActivities = '/kegiatan-ibadah';
  static const worshipAbsenceSettings = '/pengaturan-berhalangan-ibadah';
  static const worshipSchedules = '/jadwal-kegiatan-ibadah';
  static const worshipScan = '/scan-kegiatan-ibadah';
  static const privateWorshipScan = '/scan-berhalangan-ibadah';
  static const privateConfirmation = '/konfirmasi-berhalangan-ibadah';
  static const privateConfirmationDetail = '/konfirmasi-berhalangan-ibadah/:id';
  static const worshipRecap = '/rekap-kegiatan-ibadah';
  static const worshipCorrection = '/rekap-kegiatan-ibadah/koreksi/:id';
  static const worshipMonthlySummary = '/ringkasan-kegiatan-ibadah-bulanan';
  static const studentGuidanceCategories = '/kategori-pembinaan-siswa';
  static const studentViolationTypes = '/jenis-pelanggaran-siswa';
  static const pointSanctionRules = '/aturan-sanksi-poin';
  static const latePointSettings = '/pengaturan-poin-keterlambatan';
  static const earlyWarningSettings = '/pengaturan-peringatan-dini-poin';
  static const violationProcessDeadlines =
      '/pengaturan-batas-proses-pelanggaran';
  static const roleAccess = '/role-hak-akses';
  static const roleAccessDetail = '/role-hak-akses/:id';
  static const menuGroup = '/menu/:groupCode';
}

final splashGateProvider = FutureProvider<void>((ref) async {
  await Future<void>.delayed(const Duration(milliseconds: 1800));
});

final appRouterProvider = Provider<GoRouter>((ref) {
  final authAsync = ref.watch(authControllerProvider);
  final splashGate = ref.watch(splashGateProvider);
  final router = GoRouter(
    initialLocation: AppRoutes.startup,
    redirect: (context, routerState) {
      final location = routerState.matchedLocation;

      if (!splashGate.hasValue || authAsync.isLoading || authAsync.hasError) {
        return location == AppRoutes.startup ? null : AppRoutes.startup;
      }

      final auth = authAsync.value;
      final pengguna = auth?.session?.pengguna;

      if (pengguna == null) {
        return location == AppRoutes.login ? null : AppRoutes.login;
      }

      if (pengguna.wajibGantiKataSandi) {
        return location == AppRoutes.gantiKataSandi
            ? null
            : AppRoutes.gantiKataSandi;
      }

      if (location == AppRoutes.startup ||
          location == AppRoutes.login ||
          location == AppRoutes.gantiKataSandi) {
        return AppRoutes.home;
      }

      return null;
    },
    routes: [
      GoRoute(
        path: AppRoutes.startup,
        name: 'startup',
        builder: (context, state) => const StartupView(),
      ),
      GoRoute(
        path: AppRoutes.login,
        name: 'login',
        pageBuilder: (context, state) =>
            _fadePage(key: state.pageKey, child: const LoginView()),
      ),
      GoRoute(
        path: AppRoutes.gantiKataSandi,
        name: 'ganti-kata-sandi',
        builder: (context, state) => const GantiKataSandiView(),
      ),
      GoRoute(
        path: AppRoutes.home,
        name: 'home',
        pageBuilder: (context, state) =>
            _fadePage(key: state.pageKey, child: const HomeView()),
      ),
      GoRoute(
        path: AppRoutes.cbtCenter,
        name: 'cbt-center',
        builder: (context, state) =>
            const CbtCenterView(focus: CbtCenterFocus.management),
      ),
      GoRoute(
        path: AppRoutes.facilityDashboard,
        name: 'facility-dashboard',
        builder: (context, state) => const FacilityDashboardView(),
      ),
      GoRoute(
        path: AppRoutes.inventoryGoods,
        name: 'inventory-goods',
        builder: (context, state) => const InventoryGoodsView(),
      ),
      GoRoute(
        path: AppRoutes.inventoryCategories,
        name: 'inventory-categories',
        builder: (context, state) => const InventoryCategoryView(),
      ),
      GoRoute(
        path: AppRoutes.inventoryUnits,
        name: 'inventory-units',
        builder: (context, state) => const InventoryUnitView(),
      ),
      GoRoute(
        path: AppRoutes.inventoryLocations,
        name: 'inventory-locations',
        builder: (context, state) => const InventoryLocationView(),
      ),
      GoRoute(
        path: AppRoutes.inventoryAcquisitionSources,
        name: 'inventory-acquisition-sources',
        builder: (context, state) => const InventoryAcquisitionSourceView(),
      ),
      GoRoute(
        path: AppRoutes.inventorySettings,
        name: 'inventory-settings',
        builder: (context, state) => const InventorySettingsView(),
      ),
      GoRoute(
        path: AppRoutes.assetUnits,
        name: 'asset-units',
        builder: (context, state) => const AssetUnitView(),
      ),
      GoRoute(
        path: AppRoutes.inventoryLabels,
        name: 'inventory-labels',
        builder: (context, state) => InventoryLabelView(
          initialReceiptId: int.tryParse(
            state.uri.queryParameters['penerimaan_barang_id'] ?? '',
          ),
        ),
      ),
      GoRoute(
        path: AppRoutes.goodsReceipts,
        name: 'goods-receipts',
        builder: (context, state) => const GoodsReceiptView(),
      ),
      GoRoute(
        path: AppRoutes.stockBalances,
        name: 'stock-balances',
        builder: (context, state) => const StockBalanceView(),
      ),
      GoRoute(
        path: AppRoutes.stockMovements,
        name: 'stock-movements',
        builder: (context, state) => StockMovementView(
          initialGoodsId: int.tryParse(
            state.uri.queryParameters['barang_id'] ?? '',
          ),
          initialLocationId: int.tryParse(
            state.uri.queryParameters['lokasi_barang_id'] ?? '',
          ),
          openForm: state.uri.queryParameters['tambah'] == '1',
        ),
      ),
      GoRoute(
        path: AppRoutes.centralExamExecution,
        name: 'central-exam-execution',
        builder: (context, state) => const CentralExamExecutionListView(),
      ),
      GoRoute(
        path: AppRoutes.centralExamExecutionDetail,
        name: 'central-exam-execution-detail',
        builder: (context, state) => CentralExamExecutionDetailView(
          eventId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.centralExamPreparation,
        name: 'central-exam-preparation',
        builder: (context, state) => const CentralExamPreparationListView(),
      ),
      GoRoute(
        path: AppRoutes.centralExamPreparationCreate,
        name: 'central-exam-preparation-create',
        builder: (context, state) => CentralExamEventFormView(
          initialReferences: state.extra as CentralExamPreparationReferences?,
        ),
      ),
      GoRoute(
        path: AppRoutes.centralExamPreparationEdit,
        name: 'central-exam-preparation-edit',
        builder: (context, state) => CentralExamEventFormView(
          eventId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.centralExamDistributionDetail,
        name: 'central-exam-distribution-detail',
        builder: (context, state) => CentralExamDistributionDetailView(
          eventId: int.parse(state.pathParameters['eventId']!),
          groupId: int.parse(state.pathParameters['groupId']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.centralExamPreparationDetail,
        name: 'central-exam-preparation-detail',
        builder: (context, state) => CentralExamPreparationDetailView(
          eventId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.centralExamResults,
        name: 'central-exam-results',
        builder: (context, state) => const CentralExamResultsListView(),
      ),
      GoRoute(
        path: AppRoutes.centralExamCorrection,
        name: 'central-exam-correction',
        builder: (context, state) => CentralExamCorrectionView(
          eventId: int.parse(state.pathParameters['eventId']!),
          scheduleId: int.parse(state.pathParameters['scheduleId']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.centralExamResultsDetail,
        name: 'central-exam-results-detail',
        builder: (context, state) => CentralExamResultsDetailView(
          eventId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.myExamSupervision,
        name: 'my-exam-supervision',
        builder: (context, state) =>
            const CbtCenterView(focus: CbtCenterFocus.supervisor),
      ),
      GoRoute(
        path: AppRoutes.myExamSupervisionDetail,
        name: 'my-exam-supervision-detail',
        builder: (context, state) => ExamSupervisionDetailView(
          roomId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.examAttendance,
        name: 'exam-attendance',
        builder: (context, state) => const ExamAttendanceListView(),
      ),
      GoRoute(
        path: AppRoutes.examAttendanceDetail,
        name: 'exam-attendance-detail',
        builder: (context, state) => ExamAttendanceDetailView(
          roomId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.myExams,
        name: 'my-exams',
        builder: (context, state) =>
            const CbtCenterView(focus: CbtCenterFocus.student),
      ),
      GoRoute(
        path: AppRoutes.studentExam,
        name: 'student-exam',
        builder: (context, state) => StudentExamView(
          participantId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.questionBank,
        name: 'question-bank',
        builder: (context, state) => const QuestionBankListView(),
      ),
      GoRoute(
        path: AppRoutes.questionBankCreate,
        name: 'question-bank-create',
        builder: (context, state) => const QuestionBankFormView(),
      ),
      GoRoute(
        path: AppRoutes.questionBankEdit,
        name: 'question-bank-edit',
        builder: (context, state) => QuestionBankFormView(
          questionId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.questionBankDetail,
        name: 'question-bank-detail',
        builder: (context, state) => QuestionBankDetailView(
          questionId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.questionPackages,
        name: 'question-packages',
        builder: (context, state) => const QuestionPackageListView(),
      ),
      GoRoute(
        path: AppRoutes.questionPackageDetail,
        name: 'question-package-detail',
        builder: (context, state) => QuestionPackageDetailView(
          scheduleId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.classAssessments,
        name: 'class-assessments',
        builder: (context, state) => const ClassAssessmentListView(),
      ),
      GoRoute(
        path: AppRoutes.classAssessmentCreate,
        name: 'class-assessment-create',
        builder: (context, state) => const ClassAssessmentFormView(),
      ),
      GoRoute(
        path: AppRoutes.classAssessmentEdit,
        name: 'class-assessment-edit',
        builder: (context, state) => ClassAssessmentFormView(
          assessmentId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.classAssessmentQuestions,
        name: 'class-assessment-questions',
        builder: (context, state) => ClassAssessmentQuestionsView(
          assessmentId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.classAssessmentMonitoring,
        name: 'class-assessment-monitoring',
        builder: (context, state) => ClassAssessmentMonitoringView(
          assessmentId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.classAssessmentResults,
        name: 'class-assessment-results',
        builder: (context, state) => ClassAssessmentResultsView(
          assessmentId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.classAssessmentCorrection,
        name: 'class-assessment-correction',
        builder: (context, state) => ClassAssessmentCorrectionView(
          assessmentId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.classAssessmentDetail,
        name: 'class-assessment-detail',
        builder: (context, state) => ClassAssessmentDetailView(
          assessmentId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: AppRoutes.incidentReporting,
        name: 'incident-reporting',
        builder: (context, state) => const IncidentReportingView(),
      ),
      GoRoute(
        path: AppRoutes.studentReports,
        name: 'student-reports',
        builder: (context, state) => const StudentReportListView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'student-report-detail',
            builder: (context, state) => StudentReportDetailView(
              reportId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.guardianStudentReports,
        name: 'guardian-student-reports',
        builder: (context, state) => const StudentReportListView(
          scope: StudentReportScope.guardianStudents,
        ),
        routes: [
          GoRoute(
            path: ':id',
            name: 'guardian-student-report-detail',
            builder: (context, state) => StudentReportDetailView(
              reportId: int.parse(state.pathParameters['id']!),
              scope: StudentReportScope.guardianStudents,
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.reportVerification,
        name: 'report-verification',
        builder: (context, state) => const ReportVerificationListView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'report-verification-detail',
            builder: (context, state) => ReportVerificationDetailView(
              reportId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.studentAssistance,
        name: 'student-assistance',
        builder: (context, state) => const StudentAssistanceListView(),
        routes: [
          GoRoute(
            path: 'tambah',
            name: 'student-assistance-create',
            builder: (context, state) => StudentAssistanceCreateView(
              academicYearId: int.tryParse(
                state.uri.queryParameters['tahun'] ?? '',
              ),
              classId: int.tryParse(state.uri.queryParameters['kelas'] ?? ''),
              warningId: int.tryParse(
                state.uri.queryParameters['peringatan'] ?? '',
              ),
              studentId: int.tryParse(state.uri.queryParameters['siswa'] ?? ''),
              initialQuery: state.uri.queryParameters['q'] ?? '',
            ),
          ),
          GoRoute(
            path: ':id',
            name: 'student-assistance-detail',
            builder: (context, state) => StudentAssistanceDetailView(
              assistanceId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.studentEarlyWarnings,
        name: 'student-early-warnings',
        builder: (context, state) => const StudentEarlyWarningListView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'student-early-warning-detail',
            builder: (context, state) => StudentEarlyWarningDetailView(
              warningId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.studentPointRecaps,
        name: 'student-point-recaps',
        builder: (context, state) => const StudentPointRecapListView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'student-point-recap-detail',
            builder: (context, state) => StudentPointRecapDetailView(
              studentId: int.parse(state.pathParameters['id']!),
              academicYearId: int.tryParse(
                state.uri.queryParameters['tahun'] ?? '',
              ),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.pointReductions,
        name: 'point-reductions',
        builder: (context, state) => const PointReductionView(),
      ),
      GoRoute(
        path: AppRoutes.guardianAssignments,
        name: 'guardian-assignments',
        builder: (context, state) => const GuardianAssignmentListView(),
        routes: [
          GoRoute(
            path: 'tambah',
            name: 'guardian-assignment-create',
            builder: (context, state) => const GuardianAssignmentCreateView(),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.myGuardianStudents,
        name: 'my-guardian-students',
        builder: (context, state) => const MyGuardianStudentListView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'my-guardian-student-detail',
            builder: (context, state) => MyGuardianStudentDetailView(
              studentId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.studentSanctions,
        name: 'student-sanctions',
        builder: (context, state) => const StudentSanctionListView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'student-sanction-detail',
            builder: (context, state) => StudentSanctionDetailView(
              sanctionId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.employees,
        name: 'employees',
        builder: (context, state) => const EmployeeListView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'employee-detail',
            builder: (context, state) => EmployeeDetailView(
              employeeId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.employeeAccounts,
        name: 'employee-accounts',
        builder: (context, state) => const EmployeeAccountListView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'employee-account-detail',
            builder: (context, state) => EmployeeAccountDetailView(
              employeeId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.students,
        name: 'students',
        builder: (context, state) => const StudentListView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'student-detail',
            builder: (context, state) => StudentDetailView(
              studentId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.studentAccounts,
        name: 'student-accounts',
        builder: (context, state) => const StudentAccountListView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'student-account-detail',
            builder: (context, state) => StudentAccountDetailView(
              studentId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.parentAccounts,
        name: 'parent-accounts',
        builder: (context, state) => const ParentAccountListView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'parent-account-detail',
            builder: (context, state) => ParentAccountDetailView(
              studentId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.loginActivities,
        name: 'login-activities',
        builder: (context, state) => const LoginActivityListView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'login-activity-detail',
            builder: (context, state) => LoginAttemptDetailView(
              attemptId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.classes,
        name: 'classes',
        builder: (context, state) => SchoolClassListView(
          scheduleMode: state.uri.queryParameters['mode'] == 'jadwal',
        ),
        routes: [
          GoRoute(
            path: ':id',
            name: 'class-detail',
            builder: (context, state) => SchoolClassDetailView(
              classId: int.parse(state.pathParameters['id']!),
              initialTab: state.uri.queryParameters['tab'] ?? 'ringkasan',
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.academicYears,
        name: 'academic-years',
        builder: (context, state) => const AcademicYearView(),
      ),
      GoRoute(
        path: AppRoutes.classPromotion,
        name: 'class-promotion',
        builder: (context, state) => const ClassPromotionView(),
      ),
      GoRoute(
        path: AppRoutes.studentPlacement,
        name: 'student-placement',
        builder: (context, state) => const StudentPlacementView(),
      ),
      GoRoute(
        path: AppRoutes.identityPhotos,
        name: 'identity-photos',
        builder: (context, state) => const IdentityPhotoView(),
      ),
      GoRoute(
        path: AppRoutes.employeeCards,
        name: 'employee-cards',
        builder: (context, state) => const EmployeeCardView(),
      ),
      GoRoute(
        path: AppRoutes.studentCards,
        name: 'student-cards',
        builder: (context, state) => const StudentCardView(),
      ),
      GoRoute(
        path: AppRoutes.studentAttendanceSettings,
        name: 'student-attendance-settings',
        builder: (context, state) => const StudentAttendanceSettingsView(),
      ),
      GoRoute(
        path: AppRoutes.employeeAttendanceSettings,
        name: 'employee-attendance-settings',
        builder: (context, state) => const EmployeeAttendanceSettingsView(),
      ),
      GoRoute(
        path: AppRoutes.employeeAttendanceRecap,
        name: 'employee-attendance-recap',
        builder: (context, state) => const EmployeeAttendanceRecapView(),
      ),
      GoRoute(
        path: AppRoutes.employeeAttendanceReport,
        name: 'employee-attendance-report',
        builder: (context, state) => const EmployeeAttendanceReportView(),
      ),
      GoRoute(
        path: AppRoutes.employeeScanStatus,
        name: 'employee-scan-status',
        builder: (context, state) => const EmployeeScanStatusView(),
      ),
      GoRoute(
        path: AppRoutes.studentScanStatus,
        name: 'student-scan-status',
        builder: (context, state) => const StudentScanStatusView(),
      ),
      GoRoute(
        path: AppRoutes.studentAttendanceRecap,
        name: 'student-attendance-recap',
        builder: (context, state) => const StudentAttendanceRecapView(),
      ),
      GoRoute(
        path: AppRoutes.studentAttendanceReport,
        name: 'student-attendance-report',
        builder: (context, state) => const StudentAttendanceReportView(),
      ),
      GoRoute(
        path: AppRoutes.teacherDutySchedules,
        name: 'teacher-duty-schedules',
        builder: (context, state) => const TeacherDutyScheduleView(),
      ),
      GoRoute(
        path: AppRoutes.myTeacherDuty,
        name: 'my-teacher-duty',
        builder: (context, state) => const MyTeacherDutyView(),
      ),
      GoRoute(
        path: AppRoutes.lessonPeriods,
        name: 'lesson-periods',
        builder: (context, state) => const LessonPeriodView(),
      ),
      GoRoute(
        path: AppRoutes.subjects,
        name: 'subjects',
        builder: (context, state) => const SubjectView(),
      ),
      GoRoute(
        path: AppRoutes.teachingAssignments,
        name: 'teaching-assignments',
        builder: (context, state) => const TeachingAssignmentView(),
      ),
      GoRoute(
        path: AppRoutes.myTeachingSchedule,
        name: 'my-teaching-schedule',
        builder: (context, state) => const MyTeachingScheduleView(),
      ),
      GoRoute(
        path: AppRoutes.gradeWeightSchemes,
        name: 'grade-weight-schemes',
        builder: (context, state) => const GradeWeightSchemeView(),
      ),
      GoRoute(
        path: AppRoutes.gradeComponents,
        name: 'grade-components',
        builder: (context, state) => const GradeComponentView(),
      ),
      GoRoute(
        path: AppRoutes.gradeEntry,
        name: 'grade-entry',
        builder: (context, state) => const GradeEntryView(),
      ),
      GoRoute(
        path: AppRoutes.gradeRecap,
        name: 'grade-recap',
        builder: (context, state) => const GradeRecapView(),
      ),
      GoRoute(
        path: AppRoutes.myGrades,
        name: 'my-grades',
        builder: (context, state) => const MyGradesView(),
      ),
      GoRoute(
        path: AppRoutes.learningSurvey,
        name: 'learning-survey',
        builder: (context, state) => LearningSurveyView(
          assignmentId: int.parse(state.pathParameters['assignmentId']!),
          semester: state.pathParameters['semester']!,
        ),
      ),
      GoRoute(
        path: AppRoutes.surveyStatements,
        name: 'survey-statements',
        builder: (context, state) => const SurveyStatementView(),
      ),
      GoRoute(
        path: AppRoutes.surveyMonitoring,
        name: 'survey-monitoring',
        builder: (context, state) => const SurveyMonitoringView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'survey-monitoring-detail',
            builder: (context, state) => SurveyMonitoringDetailView(
              assignmentId: int.parse(state.pathParameters['id']!),
              semester: state.uri.queryParameters['semester'] ?? 'ganjil',
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.teachingDocuments,
        name: 'teaching-documents',
        builder: (context, state) => const TeachingDocumentView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'teaching-document-detail',
            builder: (context, state) => TeachingDocumentDetailView(
              documentId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.teachingDocumentReviews,
        name: 'teaching-document-reviews',
        builder: (context, state) => const TeachingDocumentReviewView(),
        routes: [
          GoRoute(
            path: 'guru/:teacherId',
            name: 'teaching-document-review-teacher',
            builder: (context, state) => TeachingDocumentTeacherDetailView(
              teacherId: int.parse(state.pathParameters['teacherId']!),
              initialAcademicYearId: int.tryParse(
                state.uri.queryParameters['tahun'] ?? '',
              ),
              initialSemester:
                  int.tryParse(state.uri.queryParameters['semester'] ?? '') ??
                  1,
            ),
          ),
          GoRoute(
            path: 'dokumen/:documentId',
            name: 'teaching-document-review-detail',
            builder: (context, state) => TeachingDocumentReviewDetailView(
              documentId: int.parse(state.pathParameters['documentId']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.teachingDocumentTypes,
        name: 'teaching-document-types',
        builder: (context, state) => const TeachingDocumentTypeView(),
      ),
      GoRoute(
        path: AppRoutes.worshipActivities,
        name: 'worship-activities',
        builder: (context, state) => const WorshipActivityView(),
      ),
      GoRoute(
        path: AppRoutes.worshipAbsenceSettings,
        name: 'worship-absence-settings',
        builder: (context, state) => const WorshipAbsenceSettingsView(),
      ),
      GoRoute(
        path: AppRoutes.worshipSchedules,
        name: 'worship-schedules',
        builder: (context, state) => const WorshipScheduleView(),
      ),
      GoRoute(
        path: AppRoutes.worshipScan,
        name: 'worship-scan',
        builder: (context, state) => const WorshipScanView(),
      ),
      GoRoute(
        path: AppRoutes.privateWorshipScan,
        name: 'private-worship-scan',
        builder: (context, state) => const PrivateWorshipScanView(),
      ),
      GoRoute(
        path: AppRoutes.privateConfirmation,
        name: 'private-confirmation',
        builder: (context, state) => const PrivateConfirmationListView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'private-confirmation-detail',
            builder: (context, state) => PrivateConfirmationDetailView(
              periodId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.worshipRecap,
        name: 'worship-recap',
        builder: (context, state) => const WorshipRecapView(),
        routes: [
          GoRoute(
            path: 'koreksi/:id',
            name: 'worship-correction',
            builder: (context, state) => WorshipCorrectionView(
              query: WorshipCorrectionQuery(
                memberId: int.parse(state.pathParameters['id']!),
                date: state.uri.queryParameters['tanggal'] ?? '',
                activityId:
                    int.tryParse(state.uri.queryParameters['kegiatan'] ?? '') ??
                    0,
              ),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.worshipMonthlySummary,
        name: 'worship-monthly-summary',
        builder: (context, state) => const WorshipMonthlySummaryView(),
      ),
      GoRoute(
        path: AppRoutes.studentGuidanceCategories,
        name: 'student-guidance-categories',
        builder: (context, state) => const StudentGuidanceCategoryView(),
      ),
      GoRoute(
        path: AppRoutes.studentViolationTypes,
        name: 'student-violation-types',
        builder: (context, state) => const StudentViolationTypeView(),
      ),
      GoRoute(
        path: AppRoutes.pointSanctionRules,
        name: 'point-sanction-rules',
        builder: (context, state) => const PointSanctionRuleView(),
      ),
      GoRoute(
        path: AppRoutes.latePointSettings,
        name: 'late-point-settings',
        builder: (context, state) => const LatePointSettingView(),
      ),
      GoRoute(
        path: AppRoutes.earlyWarningSettings,
        name: 'early-warning-settings',
        builder: (context, state) => const EarlyWarningSettingView(),
      ),
      GoRoute(
        path: AppRoutes.violationProcessDeadlines,
        name: 'violation-process-deadlines',
        builder: (context, state) => const ViolationProcessDeadlineView(),
      ),
      GoRoute(
        path: AppRoutes.roleAccess,
        name: 'role-access',
        builder: (context, state) => const RoleAccessListView(),
        routes: [
          GoRoute(
            path: ':id',
            name: 'role-access-detail',
            builder: (context, state) => RoleAccessDetailView(
              roleId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.menuGroup,
        name: 'menu-group',
        builder: (context, state) =>
            MenuGroupView(groupCode: state.pathParameters['groupCode']!),
      ),
    ],
  );

  ref.onDispose(router.dispose);

  return router;
});

CustomTransitionPage<void> _fadePage({
  required LocalKey key,
  required Widget child,
}) {
  return CustomTransitionPage<void>(
    key: key,
    child: child,
    transitionDuration: const Duration(milliseconds: 260),
    transitionsBuilder: (context, animation, secondaryAnimation, child) {
      return FadeTransition(
        opacity: CurvedAnimation(parent: animation, curve: Curves.easeOut),
        child: child,
      );
    },
  );
}
