import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/shared/widgets/nusa_account_deletion_link.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:nusa/shared/widgets/nusa_logo.dart';
import 'package:nusa/shared/widgets/nusa_privacy_policy_link.dart';
import 'package:nusa/shared/widgets/nusa_school_identity.dart';

class LoginView extends ConsumerStatefulWidget {
  const LoginView({super.key});

  @override
  ConsumerState<LoginView> createState() => _LoginViewState();
}

class _LoginViewState extends ConsumerState<LoginView> {
  final _formKey = GlobalKey<FormState>();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;

  @override
  void dispose() {
    _usernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    FocusScope.of(context).unfocus();
    await ref
        .read(authControllerProvider.notifier)
        .login(
          username: _usernameController.text,
          password: _passwordController.text,
        );
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider).value;
    final isSubmitting = auth?.isSubmitting ?? false;
    final screen = MediaQuery.sizeOf(context);
    final safeTop = MediaQuery.paddingOf(context).top;
    final headerHeight = (screen.width * 440 / 372).clamp(350.0, 525.0);

    return Scaffold(
      backgroundColor: Colors.white,
      body: Stack(
        children: [
          Positioned.fill(
            child: IgnorePointer(
              child: _LoginSchoolBackdrop(
                headerHeight: headerHeight,
                photoWidth: screen.width,
              ),
            ),
          ),
          SafeArea(
            child: LayoutBuilder(
              builder: (context, constraints) {
                final keyboardVisible =
                    MediaQuery.viewInsetsOf(context).bottom > 0;
                final compact =
                    keyboardVisible ||
                    screen.width > screen.height ||
                    constraints.maxHeight < 350;

                return SingleChildScrollView(
                  keyboardDismissBehavior:
                      ScrollViewKeyboardDismissBehavior.onDrag,
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  child: Center(
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 440),
                      child: AutofillGroup(
                        child: Form(
                          key: _formKey,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              ConstrainedBox(
                                key: const Key('login-school-header'),
                                constraints: BoxConstraints(
                                  minHeight: compact
                                      ? 0
                                      : headerHeight - safeTop,
                                ),
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  mainAxisAlignment:
                                      MainAxisAlignment.spaceBetween,
                                  children: [
                                    Padding(
                                      padding: EdgeInsets.only(
                                        top: compact
                                            ? 16
                                            : (screen.width * .105).clamp(
                                                32.0,
                                                52.0,
                                              ),
                                      ),
                                      child: const Column(
                                        children: [
                                          NusaBrand(logoSize: 56),
                                          SizedBox(height: 4),
                                          Text(
                                            'SMP Negeri 2 Padang Panjang',
                                            textAlign: TextAlign.center,
                                            style: TextStyle(
                                              color: NusaColors.primary,
                                              fontSize: 14,
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    Padding(
                                      padding: const EdgeInsets.only(top: 24),
                                      child: Column(
                                        children: [
                                          Text(
                                            'Selamat Datang',
                                            textAlign: TextAlign.center,
                                            style: Theme.of(context)
                                                .textTheme
                                                .headlineSmall
                                                ?.copyWith(
                                                  color: NusaColors.primary,
                                                  fontSize: compact ? 26 : 28,
                                                  fontWeight: FontWeight.w800,
                                                ),
                                          ),
                                          const SizedBox(height: 5),
                                          const Text(
                                            'Silakan masuk untuk melanjutkan',
                                            textAlign: TextAlign.center,
                                            style: TextStyle(
                                              color: NusaColors.textSecondary,
                                              fontSize: 14,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              if (auth?.errorMessage case final message?) ...[
                                const SizedBox(height: 12),
                                _ErrorBanner(message: message),
                              ],
                              const SizedBox(height: 16),
                              NusaTextField(
                                fieldKey: const Key('login-username'),
                                controller: _usernameController,
                                enabled: !isSubmitting,
                                hintText: 'NIP / NISN / ORT-NISN',
                                prefixIcon: Icons.person_outline_rounded,
                                autofillHints: const [AutofillHints.username],
                                textInputAction: TextInputAction.next,
                                errorText: auth?.fieldError('username'),
                                validator: (value) =>
                                    value == null || value.trim().isEmpty
                                    ? 'NIP, NISN, atau ORT-NISN wajib diisi.'
                                    : null,
                              ),
                              const SizedBox(height: 14),
                              NusaTextField(
                                fieldKey: const Key('login-password'),
                                controller: _passwordController,
                                enabled: !isSubmitting,
                                hintText: 'Kata Sandi',
                                prefixIcon: Icons.lock_outline_rounded,
                                obscureText: _obscurePassword,
                                autofillHints: const [AutofillHints.password],
                                textInputAction: TextInputAction.done,
                                onFieldSubmitted: (_) => _submit(),
                                suffixIcon: IconButton(
                                  onPressed: () => setState(
                                    () => _obscurePassword = !_obscurePassword,
                                  ),
                                  icon: Icon(
                                    _obscurePassword
                                        ? Icons.visibility_outlined
                                        : Icons.visibility_off_outlined,
                                  ),
                                  tooltip: _obscurePassword
                                      ? 'Tampilkan kata sandi'
                                      : 'Sembunyikan kata sandi',
                                ),
                                validator: (value) =>
                                    value == null || value.isEmpty
                                    ? 'Kata sandi wajib diisi.'
                                    : null,
                              ),
                              const SizedBox(height: 20),
                              NusaPrimaryButton(
                                key: const Key('login-submit'),
                                label: 'Masuk',
                                loading: isSubmitting,
                                onPressed: isSubmitting ? null : _submit,
                              ),
                              const SizedBox(height: 12),
                              const Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    Icons.info_outline_rounded,
                                    size: 16,
                                    color: NusaColors.textSecondary,
                                  ),
                                  SizedBox(width: 6),
                                  Flexible(
                                    child: Text(
                                      'Lupa kata sandi? Silakan hubungi administrator sekolah.',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(
                                        color: NusaColors.textSecondary,
                                        fontSize: 12,
                                        height: 1.35,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const Padding(
                                padding: EdgeInsets.only(top: 20, bottom: 12),
                                child: NusaTagline(),
                              ),
                              const SizedBox(height: 10),
                              const NusaPrivacyPolicyLink(
                                key: Key('login-privacy-policy'),
                              ),
                              const NusaAccountDeletionLink(
                                key: Key('login-account-deletion'),
                              ),
                              const SizedBox(height: 4),
                              const Text(
                                'Tim Teknisi SMP Negeri 2 Padang Panjang',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  color: NusaColors.textSecondary,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(height: 16),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _LoginSchoolBackdrop extends StatelessWidget {
  const _LoginSchoolBackdrop({
    required this.headerHeight,
    required this.photoWidth,
  });

  final double headerHeight;
  final double photoWidth;

  @override
  Widget build(BuildContext context) => Stack(
    fit: StackFit.expand,
    children: [
      const Opacity(
        opacity: .08,
        child: NusaSchoolPhoto(alignment: Alignment(0, .1)),
      ),
      Positioned(
        top: 0,
        left: 0,
        right: 0,
        height: headerHeight + 32,
        child: const DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Colors.white, Colors.white, Color(0x00FFFFFF)],
              stops: [0, .9, 1],
            ),
          ),
        ),
      ),
      Positioned(
        top: 0,
        left: 0,
        right: 0,
        height: headerHeight + 16,
        child: ShaderMask(
          blendMode: BlendMode.dstIn,
          shaderCallback: (bounds) => const LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Colors.white,
              Colors.white,
              Color(0x00FFFFFF),
              Color(0x00FFFFFF),
            ],
            stops: [0, .68, .9, 1],
          ).createShader(bounds),
          child: ClipRect(
            child: Stack(
              fit: StackFit.expand,
              children: [
                Positioned(
                  top: -photoWidth * .116,
                  left: 0,
                  right: 0,
                  height: photoWidth * 1.25,
                  child: const NusaSchoolPhoto(
                    asset: NusaSchoolPhoto.loginAssetPath,
                    alignment: Alignment.topCenter,
                  ),
                ),
                const ColoredBox(color: Color(0x80FFFFFF)),
              ],
            ),
          ),
        ),
      ),
    ],
  );
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: colors.errorContainer,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(message, style: TextStyle(color: colors.onErrorContainer)),
    );
  }
}
