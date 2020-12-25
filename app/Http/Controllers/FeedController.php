<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Post;
use App\PostLike;
use App\PostComment;
use App\UserRelation;
use App\User;
use Image;

class FeedController extends Controller
{
    private $loggedUser;

    public function __construct() {
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

    public function create(Request $request) {
        $array = ['error'=>''];
        // Formatos de imagens que o sistema aceita.
        $allowedTypes = ['image/jpg', 'image/jpeg', 'image/png'];

        $type = $request->input('type');
        $body = $request->input('body');
        $photo = $request->file('photo');

        if ($type) {

            switch($type) {
                case 'text':
                    // Verifica se existe um body
                    if (!$body) {
                        $array['error'] = 'Texto não enviado';
                        return $array;
                    }
                break;
                case 'photo':
                    if ($photo) {

                        if (in_array($photo->getClientMimeType(), $allowedTypes)) {

                            $filename = md5(time().rand(0,9999)).'.jpg';

                            $destPath = public_path('/media/uploads');

                            $img = Image::make($photo->path())
                                // Imagem vai ter no maximo 800 de largura e altura vai ser proporcional
                                ->resize(800, null, function($constraint) {
                                    // constraint = Vai fazer com que minha imagem mantenha a proporção
                                    $constraint->aspectRatio();
                                })
                                ->save($destPath.'/'.$filename);

                            $body = $filename;    

                        } else {    
                            $array['error'] = 'Arquivo não suportado';
                            return $array;
                        }

                    } else {
                        $array['error'] = 'Arquivo não enviado';
                        return $array;
                    }
                break;
                default:
                    $array['error'] = 'Tipo de postagem inexistente.';
                    return $array;
                break;
            }

            if ($body) {
                $newPost = new Post();
                $newPost->id_user = $this->loggedUser['id'];
                $newPost->type = $type;
                $newPost->created_at = date('Y-m-d H:i:s');
                $newPost->body = $body;
                $newPost->save();
            }

        } else {
            $array['error'] = 'Dados não enviados';
            return $array;
        }

        return $array;
    }

    public function read(Request $request) {
        $array = ['error'=>''];

        // Se o usuário não mandar nada o page vai ser ZERO
        $page = intval($request->input('page'));
        // Quantos post por página
        $perPage = 2;

        // 1. Pegar a lista de usuários que EU sigo (Incluindo EU mesmo)
        $users = [];
        $userList = UserRelation::where('user_from', $this->loggedUser['id'])->get();
        foreach($userList as $userItem) {
            $users[] = $userItem['user_to'];
        }
        $users[] = $this->loggedUser['id'];

        // 2. Pegar os posts dessa galera ORDENADO PELA DATA
        $postList = Post::whereIn('id_user', $users)
        ->orderBy('created_at', 'desc')
        ->offset($page * $perPage)
        ->limit($perPage)
        ->get();

        // Pega o total de post
        $total = Post::whereIn('id_user', $users)->count();
        $pageCount = ceil($total / $perPage);

        // 3. Preencher as informações adicionais
        $posts = $this->_postListToObject($postList, $this->loggedUser['id']);

        $array['posts'] = $posts;
        $array['pageCount'] = $pageCount;
        $array['currentPage'] = $page;

        return $array;
    }

    private function _postListToObject($postList, $loggedId) {
        foreach($postList as $postKey => $postItem) {

            // Verifica se o post é meu
            if ($postItem['id_user'] == $loggedId) {
                $postList[$postKey]['mine'] = true;
            } else {
                $postList[$postKey]['mine'] = false;
            }

            // Preencher informações de usuário
            $userInfo = User::find($postItem['id_user']);
            $userInfo['avatar'] = url('media/avatars/'.$userInfo['avatar']);
            $userInfo['cover'] = url('media/covers/'.$userInfo['cover']);
            $postList[$postKey]['user'] = $userInfo;

            // Preencher informações de LIKE
            $likes = PostLike::where('id_post', $postItem['id'])->count();
            $postList[$postKey]['likeCount'] = $likes;

            // Verifica se eu curti o Post
            $isLiked = PostLike::where('id_post', $postItem['id'])
            ->where('id_user', $loggedId)
            ->count();
            $postList[$postKey]['liked'] = ($isLiked > 0) ? true : false;

            // Preencher informações de COMMENTS
            $comments = PostComment::where('id_post', $postItem['id'])->get();
            foreach($comments as $commentKey => $comment) {
                $user = User::find($comment['id_user']);
                $user['avatar'] = url('media/avatars/'.$user['avatar']);
                $user['cover'] = url('media/covers/'.$user['cover']);
                $comments[$commentKey]['user'] = $user;
            }
            $postList[$postKey]['comments'] = $comments;

        }

        return $postList;
    }
}
