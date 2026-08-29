import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/auth/presentation/ganti_kata_sandi_view.dart';
import 'package:nusa/features/auth/presentation/login_view.dart';
import 'package:nusa/features/auth/presentation/startup_view.dart';
import 'package:nusa/features/academic_year/presentation/academic_year_view.dart';
import 'package:nusa/features/class_promotion/presentation/class_promotion_view.dart';
import 'package:nusa/features/employee/presentation/employee_detail_view.dart';
import 'package:nusa/features/employee/presentation/employee_list_view.dart';
import 'package:nusa/features/employee_card/presentation/employee_card_view.dart';
import 'package:nusa/features/employee_attendance_settings/presentation/employee_attendance_settings_view.dart';
import 'package:nusa/features/employee_attendance_recap/presentation/employee_attendance_recap_view.dart';
import 'package:nusa/features/employee_attendance_report/presentation/employee_attendance_report_view.dart';
import 'package:nusa/features/employee_scan_status/presentation/employee_scan_status_view.dart';
import 'package:nusa/features/employee_account/presentation/employee_account_detail_view.dart';
import 'package:nusa/features/employee_account/presentation/employee_account_list_view.dart';
import 'package:nusa/features/grade_weight_scheme/presentation/grade_weight_scheme_view.dart';
import 'package:nusa/features/grade_component/presentation/grade_component_view.dart';
import 'package:nusa/features/grade_entry/presentation/grade_entry_view.dart';
import 'package:nusa/features/grade_recap/presentation/grade_recap_view.dart';
import 'package:nusa/features/home/presentation/home_view.dart';
import 'package:nusa/features/identity_photo/presentation/identity_photo_view.dart';
import 'package:nusa/features/lesson_period/presentation/lesson_period_view.dart';
import 'package:nusa/features/learning_survey/presentation/learning_survey_view.dart';
import 'package:nusa/features/login_activity/presentation/login_activity_list_view.dart';
import 'package:nusa/features/login_activity/presentation/login_attempt_detail_view.dart';
import 'package:nusa/features/menu/presentation/menu_group_view.dart';
import 'package:nusa/features/my_teaching_schedule/presentation/my_teaching_schedule_view.dart';
import 'package:nusa/features/my_grades/presentation/my_grades_view.dart';
import 'package:nusa/features/parent_account/presentation/parent_account_detail_view.dart';
import 'package:nusa/features/parent_account/presentation/parent_account_list_view.dart';
import 'package:nusa/features/private_worship_scan/presentation/private_worship_scan_view.dart';
import 'package:nusa/features/private_confirmation/presentation/private_confirmation_detail_view.dart';
import 'package:nusa/features/private_confirmation/presentation/private_confirmation_list_view.dart';
import 'package:nusa/features/role_access/presentation/role_access_detail_view.dart';
import 'package:nusa/features/role_access/presentation/role_access_list_view.dart';
import 'package:nusa/features/school_class/presentation/school_class_detail_view.dart';
import 'package:nusa/features/school_class/presentation/school_class_list_view.dart';
import 'package:nusa/features/student/presentation/student_detail_view.dart';
import 'package:nusa/features/student/presentation/student_list_view.dart';
import 'package:nusa/features/student_account/presentation/student_account_detail_view.dart';
import 'package:nusa/features/student_account/presentation/student_account_list_view.dart';
import 'package:nusa/features/student_attendance_settings/presentation/student_attendance_settings_view.dart';
import 'package:nusa/features/student_attendance_recap/presentation/student_attendance_recap_view.dart';
import 'package:nusa/features/student_attendance_report/presentation/student_attendance_report_view.dart';
import 'package:nusa/features/student_scan_status/presentation/student_scan_status_view.dart';
import 'package:nusa/features/student_card/presentation/student_card_view.dart';
import 'package:nusa/features/student_placement/presentation/student_placement_view.dart';
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
import 'package:nusa/features/worship_schedule/presentation/worship_schedule_view.dart';
import 'package:nusa/features/worship_scan/presentation/worship_scan_view.dart';

abstract final class AppRoutes {
  static const startup = '/startup';
  static const login = '/login';
  static const gantiKataSandi = '/ganti-kata-sandi';
  static const home = '/beranda';
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
