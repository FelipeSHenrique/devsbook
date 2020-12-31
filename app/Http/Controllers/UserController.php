<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\User;
use App\Post;
use App\PostLike;
use App\PostComment;
use App\UserRelation;
// Biblioteca de imagens
use Image;

class UserController extends Controller
{
    private $loggedUser;

    public function __construct() {
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

    public function update(Request $request) {
        $array = ['error' => ''];

        $name = $request->input('name'); 
        $email = $request->input('email');
        $birthdate = $request->input('birthdate');
        $city = $request->input('city');
        $work = $request->input('work');
        $password = $request->input('password');
        $password_confirm = $request->input('password_confirm');

        $user = User::find($this->loggedUser['id']);

        // NAME
        if ($name) {
            $user->name = $name;
        }

        // E-MAIL
        if ($email) {
            if ($email != $user->email) {
                $emailExists = User::where('email', $email)->count();
                if ($emailExists === 0) {
                    $user->email = $email;
                } else {
                    $array['error'] = 'E-mail já existe!';
                    return $array;
                }
            }
        }

        // BIRTHDATE
        if ($birthdate) {
            if (strtotime($birthdate) === false) {
                $array['error'] = 'Data de nascimento inválida';
                return $array;
            }
            $user->birthdate = $birthdate;
        }

        // CITY
        if ($city) {
            $user->city = $city;
        }

        //WORK
        if ($work) {
            $user->work = $work;
        }

        // PASSWORD
        if ($password && $password_confirm) {
            if ($password === $password_confirm) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $user->password = $hash;
            } else {
                $array['error'] = 'As senhas não batem.';
                return $array;
            }
        }

        $user->save();
        
        return $array;
    }

    public function updateAvatar(Request $request) {
        $array = ['error'=>''];
        $allowedTypes = ['image/jpg', 'image/jpeg', 'image/png'];

        $image = $request->file('avatar');

        if ($image) {
            // getClientMimeType = Verifica qual o tipo do arquivo (https://www.php.net/manual/en/function.mime-content-type.php)
            // Verificando se o arquivo é do tipo jpg/jpeg/png
            if (in_array($image->getClientMimeType(), $allowedTypes)) {
                
                //Gerar nome aleatorio para o arquivo
                $filename = md5(time().rand(0,9999)).'.jpg';

                // Arquivo que vou salvar a imagem
                $destPath = public_path('/media/avatars');

                // Mando o arquivo original que fiz o upload
                $img = Image::make($image->path())
                // Tamanho da imagem
                    ->fit(200, 200)    
                    ->save($destPath.'/'.$filename);

                $user = User::find($this->loggedUser['id']);
                $user->avatar = $filename;
                $user->save();
                
                $array['url'] = url('/media/avatars/'.$filename);

            } else {
                $array['error'] = 'Arquivo não suportado!';
                return $array;
            }
        } else {
            $array['error'] = 'Arquivo não enviado!';
            return $array;
        }

        return $array;
    }

    public function updateCover(Request $request) {
        $array = ['error'=>''];
        // Formatos de imagens que o sistema aceita.
        $allowedTypes = ['image/jpg', 'image/jpeg', 'image/png'];

        $image = $request->file('cover');

        if ($image) {
            // getClientMimeType = Verifica qual o tipo do arquivo (https://www.php.net/manual/en/function.mime-content-type.php)
            // Verificando se o arquivo é do tipo jpg/jpeg/png
            if (in_array($image->getClientMimeType(), $allowedTypes)) {
                
                //Gerar nome aleatorio para o arquivo
                $filename = md5(time().rand(0,9999)).'.jpg';

                // Arquivo que vou salvar a imagem
                $destPath = public_path('/media/covers');

                // Mando o arquivo original que fiz o upload
                $img = Image::make($image->path())
                // Tamanho da imagem
                    ->fit(850, 310)    
                    ->save($destPath.'/'.$filename);

                $user = User::find($this->loggedUser['id']);
                $user->cover = $filename;
                $user->save();
                
                $array['url'] = url('/media/covers/'.$filename);

            } else {
                $array['error'] = 'Arquivo não suportado!';
                return $array;
            }
        } else {
            $array['error'] = 'Arquivo não enviado!';
            return $array;
        }

        return $array;
    }

    public function read($id = false) {
        // GET api/user
        // GET api/user/123
        $array = ['error'=>''];

        if ($id) {
            // Pega todas as informaçções de um usuário especifico
            $info = User::find($id);
            if (!$info) {
                $array['error'] = 'Usuário inexistente';
                return $array;
            }
        } else {
            // Pega as informações do usuário que está logado no momento
            $info = $this->loggedUser;
        }

        $info['avatar'] = url('media/avatars/'.$info['avatar']);
        $info['cover'] = url('media/covers/'.$info['cover']);

        $info['me'] = ($info['id'] == $this->loggedUser['id']) ? true : false;

        // Pegar quando anos o usuário tem usando a data de nascimento
        $dateFrom = new \DateTime($info['birthdate']);
        $dateTo = new \DateTime('today');
        $info['age'] = $dateFrom->diff($dateTo)->y;

        // Pegar a quantidade total de pessoas que me seguem
        $info['followers'] = UserRelation::where('user_to', $info['id'])->count();
        // Pegar a quantidade total de pessoas que eu sigo
        $info['following'] = UserRelation::where('user_from', $info['id'])->count();

        $info['photoCount'] = Post::where('id_user', $info['id'])
        ->where('type', 'photo')
        ->count();  
        
        // Verifica se eu já sigo o usuário no qual estou visitando o perfil
        $hasRelation = UserRelation::where('user_from', $this->loggedUser['id'])
        ->where('user_to', $info['id'])
        ->count();
        $info['isFollowing'] = ($hasRelation > 0) ? true : false;

        $array['data'] = $info;

        return $array;
    }

    public function follow($id) {
        $array = ['error'=>''];

        // VERIFICAR SE NÃO ESTOU SEGUINDO EU MESMO
        if ($id == $this->loggedUser['id']) {
            $array['error'] = 'Você não pode seguir a si mesmo.';
            return $array;
        }

        $userExists = User::find($id);
        if ($userExists) {

            $relation = UserRelation::where('user_from', $this->loggedUser['id'])
            ->where('user_to', $id)
            ->first();

            if ($relation) {
                // Parar de seguir
                $relation->delete();
            } else {
                // Seguir
                $newRelation = new UserRelation();
                $newRelation->user_from = $this->loggedUser['id'];
                $newRelation->user_to = $id;
                $newRelation->save();
            }

        } else {
            $array['error'] = 'Usuário inexistente!';
            return $array;
        }

        return $array;
    }

    public function followers($id) {
        $array = ['error'=>''];

        $userExists = User::find($id);
        if ($userExists) {

            $followers = UserRelation::where('user_to', $id)->get();
            $following = UserRelation::where('user_from', $id)->get();

            $array['followers'] = [];
            $array['following'] = [];

            foreach($followers as $item) {
                $user = User::find($item['user_from']);
                $array['followers'][] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'avatar' => url('media/avatars/'.$user['avatar'])
                ];
            }

            foreach($following as $item) {
                $user = User::find($item['user_from']);
                $array['following'][] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'avatar' => url('media/avatars/'.$user['avatar'])
                ];
            }

        } else {
            $array['error'] = 'Usuário inexistente!';
            return $array;
        }

        return $array;
    }

}
