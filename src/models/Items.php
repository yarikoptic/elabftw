<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Models;

use Elabftw\Elabftw\Metadata;
use Elabftw\Elabftw\Tools;
use Elabftw\Enums\EntityType;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Traits\InsertTagsTrait;
use PDO;

/**
 * All about the database items
 */
class Items extends AbstractConcreteEntity
{
    use InsertTagsTrait;

    public function __construct(Users $users, ?int $id = null)
    {
        $this->type = EntityType::Items->value;
        $this->page = 'database';
        parent::__construct($users, $id);
    }

    // special case for items where the page property is not the correct endpoint
    public function getPage(): string
    {
        return sprintf('api/v2/%s/', EntityType::Items->value);
    }

    public function create(int $template, array $tags = array()): int
    {
        $ItemsTypes = new ItemsTypes($this->Users, $template);
        $itemTemplate = $ItemsTypes->readOne();

        $sql = 'INSERT INTO items(team, title, date, body, userid, category, elabid, canread, canwrite, metadata)
            VALUES(:team, :title, CURDATE(), :body, :userid, :category, :elabid, :canread, :canwrite, :metadata)';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':team', $this->Users->userData['team'], PDO::PARAM_INT);
        $req->bindValue(':title', _('Untitled'), PDO::PARAM_STR);
        $req->bindParam(':body', $itemTemplate['body'], PDO::PARAM_STR);
        $req->bindParam(':category', $template, PDO::PARAM_INT);
        $req->bindValue(':elabid', Tools::generateElabid(), PDO::PARAM_STR);
        $req->bindParam(':canread', $itemTemplate['canread'], PDO::PARAM_STR);
        $req->bindParam(':canwrite', $itemTemplate['canwrite'], PDO::PARAM_STR);
        $req->bindParam(':metadata', $itemTemplate['metadata'], PDO::PARAM_STR);
        $req->bindParam(':userid', $this->Users->userData['userid'], PDO::PARAM_INT);
        $this->Db->execute($req);
        $newId = $this->Db->lastInsertId();

        $this->insertTags($tags, $newId);
        $this->ItemsLinks->duplicate($itemTemplate['id'], $newId, true);
        $this->Steps->duplicate($itemTemplate['id'], $newId, true);

        return $newId;
    }

    public function duplicate(): int
    {
        $this->canOrExplode('read');

        // handle the blank_value_on_duplicate attribute on extra fields
        $metadata = (new Metadata($this->entityData['metadata']))->blankExtraFieldsValueOnDuplicate();

        $sql = 'INSERT INTO items(team, title, date, body, userid, canread, canwrite, category, elabid, metadata, content_type)
            VALUES(:team, :title, CURDATE(), :body, :userid, :canread, :canwrite, :category, :elabid, :metadata, :content_type)';
        $req = $this->Db->prepare($sql);
        $req->execute(array(
            'team' => $this->Users->userData['team'],
            'title' => $this->entityData['title'],
            'body' => $this->entityData['body'],
            'userid' => $this->Users->userData['userid'],
            'elabid' => Tools::generateElabid(),
            'canread' => $this->entityData['canread'],
            'canwrite' => $this->entityData['canwrite'],
            'category' => $this->entityData['category_id'],
            'metadata' => $metadata,
            'content_type' => $this->entityData['content_type'],
        ));
        $newId = $this->Db->lastInsertId();

        if ($this->id === null) {
            throw new IllegalActionException('Try to duplicate without an id.');
        }
        $this->ItemsLinks->duplicate($this->id, $newId);
        $this->Steps->duplicate($this->id, $newId);
        $this->Tags->copyTags($newId);

        return $newId;
    }

    public function destroy(): bool
    {
        // check if we can actually delete items (for non-admins)
        $Teams = new Teams($this->Users);
        $teamConfigArr = $Teams->readOne();
        if ($teamConfigArr['deletable_item'] === 0 && !$this->Users->isAdmin) {
            throw new ImproperActionException(_('Users cannot delete items.'));
        }

        parent::destroy();

        // delete links of this item in experiments with this item linked
        $sql = 'DELETE FROM experiments_links WHERE link_id = :link_id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':link_id', $this->id, PDO::PARAM_INT);
        $this->Db->execute($req);
        // same for items_links
        $sql = 'DELETE FROM items_links WHERE link_id = :link_id';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':link_id', $this->id, PDO::PARAM_INT);
        $this->Db->execute($req);

        // delete from pinned
        return $this->Pins->cleanup();
    }
}
