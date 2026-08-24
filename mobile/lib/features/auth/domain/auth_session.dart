import 'package:nusa/features/auth/domain/pengguna.dart';

class AuthSession {
  const AuthSession({required this.token, required this.pengguna});

  final String token;
  final Pengguna pengguna;

  AuthSession copyWith({Pengguna? pengguna}) {
    return AuthSession(token: token, pengguna: pengguna ?? this.pengguna);
  }
}
