<?php
// Mensagens informacionais detalhadas por código de erro.
return [
    '401' => 'O erro 401 significa que o sistema exige identificação válida para permitir o acesso ao conteúdo solicitado. Em regra, ocorre quando a sessão expirou, o usuário não realizou login ou as credenciais informadas não foram reconhecidas. É um mecanismo de proteção, semelhante ao controle de acesso a áreas restritas: é necessário possuir autorização formal para ingressar. Para resolver, realize novo login e verifique se seu perfil está adequado às permissões exigidas. Persistindo o problema, contate o suporte responsável pela gestão de usuários do sistema.',
    '403' => 'O erro 403 indica que, embora sua solicitação tenha sido entendida, você não possui as permissões necessárias para acessar este recurso. Verifique se está autenticado com a conta correta e, se necessário, solicite autorização ao responsável pelo sistema.',
    '404' => 'O erro 404 significa que o recurso solicitado não foi encontrado no servidor. Pode ter sido removido, renomeado ou o endereço digitado estar incorreto. Verifique o URL, utilize a navegação do site ou retorne à página inicial.',
    '500' => 'O erro 500 indica que ocorreu uma falha inesperada no servidor ao processar sua solicitação. Nossos logs e equipe técnica foram notificados. Anote a hora e o endpoint solicitado e reenvie ao suporte se necessário.',
    '503' => 'O erro 503 indica que o serviço está temporariamente indisponível, geralmente devido a manutenção ou sobrecarga. Tente novamente mais tarde; se o problema persistir, consulte o status do sistema ou entre em contato com o suporte.',
];

