class DeleteTagsModalController {
  API: APIService;
  userId: number;
  resolve: any;
  close: any;
  dismiss: any;

  constructor(API: APIService) {
    this.API = API;
  }

  $onInit() {
    this.userId = this.resolve.userId;
  }

  deleteAllTags(): void {
    this.API.delete(`tags/${this.userId}`);
    this.close({$value: 'ok'});
  }
}

angular.module('crucioApp').component('deleteTagsModalComponent', {
  templateUrl: 'app/user/delete-tags-modal.html',
  controller: DeleteTagsModalController,
  bindings: {
    resolve: '<',
    close: '&',
    dismiss: '&'
  }
});