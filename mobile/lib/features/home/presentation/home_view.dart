import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/home/application/home_controller.dart';
import 'package:nusa/features/home/presentation/home_dashboard_view.dart';
import 'package:nusa/features/home/presentation/home_tabs.dart';
import 'package:nusa/features/menu/application/menu_controller.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';
import 'package:nusa/features/menu/presentation/menu_view.dart';
import 'package:nusa/shared/widgets/nusa_bottom_navigation.dart';

class HomeView extends ConsumerStatefulWidget {
  const HomeView({super.key});

  @override
  ConsumerState<HomeView> createState() => _HomeViewState();
}

class _HomeViewState extends ConsumerState<HomeView> {
  int _selectedIndex = 0;

  Future<void> _refreshDashboard() {
    return ref.read(homeControllerProvider.notifier).refresh();
  }

  Future<void> _refreshMenu() {
    return ref.read(menuControllerProvider.notifier).refresh();
  }

  Future<void> _refreshHome() async {
    await Future.wait([_refreshDashboard(), _refreshMenu()]);
  }

  Future<void> _confirmLogout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Keluar dari NUSA?'),
        content: const Text(
          'Sesi pada perangkat ini akan dihapus. Anda perlu masuk kembali '
          'untuk menggunakan NUSA.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Keluar'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await ref.read(authControllerProvider.notifier).logout();
    }
  }

  void _showUnavailable(String feature) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(
            '$feature belum tersedia di aplikasi mobile. '
            'Fitur ini akan dibangun pada tahap berikutnya.',
          ),
        ),
      );
  }

  void _openMenuEntry(MenuEntry item) {
    if (item.isAvailable) {
      context.push(item.route!);
      return;
    }

    _showUnavailable(item.label);
  }

  void _openMenuGroup(MenuGroup group) {
    context.push('/menu/${group.code}');
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider).value;
    final pengguna = auth?.session?.pengguna;
    final dashboard = ref.watch(homeControllerProvider);
    final menu = ref.watch(menuControllerProvider);
    final unreadCount = dashboard.value?.notifications.unreadCount ?? 0;

    if (pengguna == null) {
      return const Scaffold(body: SizedBox.shrink());
    }

    return Scaffold(
      backgroundColor: NusaColors.background,
      body: IndexedStack(
        index: _selectedIndex,
        children: [
          HomeDashboardView(
            pengguna: pengguna,
            dashboard: dashboard,
            menu: menu,
            onRefresh: _refreshHome,
            onOpenActivity: () => setState(() => _selectedIndex = 1),
            onOpenNusa: () => setState(() => _selectedIndex = 2),
            onOpenNotifications: () => setState(() => _selectedIndex = 3),
            onOpenMenuGroup: _openMenuGroup,
            onOpenMenuEntry: _openMenuEntry,
            onUnavailable: _showUnavailable,
          ),
          NusaPageFrame(
            title: 'Aktivitas',
            action: IconButton(
              onPressed: dashboard.isLoading ? null : _refreshDashboard,
              icon: const Icon(Icons.refresh_rounded),
              tooltip: 'Perbarui',
            ),
            child: ActivityPage(
              dashboard: dashboard,
              onRefresh: _refreshDashboard,
            ),
          ),
          NusaPageFrame(
            title: 'Menu NUSA',
            action: IconButton(
              onPressed: menu.isLoading ? null : _refreshMenu,
              icon: const Icon(Icons.refresh_rounded),
              tooltip: 'Perbarui',
            ),
            child: MenuView(
              catalog: menu,
              onRefresh: _refreshMenu,
              onOpenGroup: _openMenuGroup,
              onOpen: _openMenuEntry,
            ),
          ),
          NusaPageFrame(
            title: 'Notifikasi',
            action: IconButton(
              onPressed: dashboard.isLoading ? null : _refreshDashboard,
              icon: const Icon(Icons.refresh_rounded),
              tooltip: 'Perbarui',
            ),
            child: NotificationsPage(
              dashboard: dashboard,
              onRefresh: _refreshDashboard,
            ),
          ),
          NusaPageFrame(
            title: 'Profil',
            child: ProfilePage(
              pengguna: pengguna,
              employee: dashboard.value?.employee,
              onRefresh: _refreshDashboard,
              onLogout: _confirmLogout,
              isLoggingOut: auth?.isSubmitting ?? false,
            ),
          ),
        ],
      ),
      bottomNavigationBar: NusaBottomNavigation(
        selectedIndex: _selectedIndex,
        unreadCount: unreadCount,
        onSelected: (index) => setState(() => _selectedIndex = index),
      ),
    );
  }
}
