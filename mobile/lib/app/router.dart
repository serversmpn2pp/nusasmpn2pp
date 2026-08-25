import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/auth/presentation/ganti_kata_sandi_view.dart';
import 'package:nusa/features/auth/presentation/login_view.dart';
import 'package:nusa/features/auth/presentation/startup_view.dart';
import 'package:nusa/features/academic_year/presentation/academic_year_view.dart';
import 'package:nusa/features/employee/presentation/employee_detail_view.dart';
import 'package:nusa/features/employee/presentation/employee_list_view.dart';
import 'package:nusa/features/employee_account/presentation/employee_account_detail_view.dart';
import 'package:nusa/features/employee_account/presentation/employee_account_list_view.dart';
import 'package:nusa/features/home/presentation/home_view.dart';
import 'package:nusa/features/lesson_period/presentation/lesson_period_view.dart';
import 'package:nusa/features/menu/presentation/menu_group_view.dart';
import 'package:nusa/features/role_access/presentation/role_access_detail_view.dart';
import 'package:nusa/features/role_access/presentation/role_access_list_view.dart';
import 'package:nusa/features/school_class/presentation/school_class_detail_view.dart';
import 'package:nusa/features/school_class/presentation/school_class_list_view.dart';
import 'package:nusa/features/student/presentation/student_detail_view.dart';
import 'package:nusa/features/student/presentation/student_list_view.dart';
import 'package:nusa/features/subject/presentation/subject_view.dart';
import 'package:nusa/features/teaching_assignment/presentation/teaching_assignment_view.dart';

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
  static const classes = '/kelas';
  static const academicYears = '/tahun-pelajaran';
  static const classDetail = '/kelas/:id';
  static const lessonPeriods = '/jam-pelajaran';
  static const subjects = '/mata-pelajaran';
  static const teachingAssignments = '/guru-mata-pelajaran';
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
