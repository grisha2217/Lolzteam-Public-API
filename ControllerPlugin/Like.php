<?php

namespace Xfrocks\Api\ControllerPlugin;

use Xfrocks\Api\Controller\AbstractController;

class Like extends \XF\ControllerPlugin\Reaction
{
    /**
     * @param \XF\Mvc\Entity\Entity $content
     * @param bool $insert true to insert, false to delete
     * @return \XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionToggleLike($content, $insert)
    {
        $requestedReaction = $this->validateReactionAction($content);

        $reactionRepo = $this->getReactionRepo();
        $contentType = $content->getEntityContentType();
        $contentId = $content->getEntityId();
        $reactUser = \XF::visitor();

        $existingReaction = $reactionRepo->getReactionByContentAndReactionUser(
            $contentType,
            intval($contentId),
            $reactUser->user_id
        );

        if ($insert === ($existingReaction === null)) {
            $reactionRepo->reactToContent(
                $requestedReaction->reaction_id,
                $contentType,
                $contentId,
                $reactUser,
                true
            );
        }

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @param mixed $key
     * @param mixed $type
     * @param mixed $default
     * @return mixed
     */
    public function filter($key, $type = null, $default = null)
    {
        if ($key === 'reaction_id') {
            return 1;
        }

        return parent::filter($key, $type, $default);
    }
}